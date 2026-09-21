import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import { useCallback, useEffect, useMemo, useState, useRef } from "react";
import {
    AlertTriangle,
    Ban,
    Check,
    Clock3,
    Home,
    Loader2,
    PackageCheck,
} from "lucide-react";
import { toast } from "sonner";
import { gsap } from "gsap";

import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { Label } from "@/Components/ui/label";
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import { cn } from "@/lib/utils";

const STATUS_META = {
    in_stock: {
        label: "In Stock",
        badgeClass: "bg-emerald-50 text-emerald-700 border-emerald-200",
    },
    almost_out: {
        label: "Almost Out",
        badgeClass: "bg-amber-50 text-amber-700 border-amber-200",
    },
    out_of_stock: {
        label: "Out of Stock",
        badgeClass: "bg-rose-50 text-rose-700 border-rose-200",
    },
};

const INACTIVE_ACTION_CLASS =
    "border-input bg-background text-foreground hover:bg-accent";

// Each action is only offered where it would actually change something, so a
// barista is never asked to re-report a status the item already has.
const ACTIONS = [
    {
        label: "Almost Out",
        status: "ALMOST_OUT",
        icon: AlertTriangle,
        activeClass:
            "border-amber-500 bg-amber-50 text-amber-700 hover:bg-amber-100",
        appliesTo: ["in_stock"],
    },
    {
        label: "Out of Stock",
        status: "OUT",
        icon: Ban,
        activeClass:
            "border-rose-500 bg-rose-50 text-rose-700 hover:bg-rose-100",
        appliesTo: ["in_stock", "almost_out"],
    },
    {
        label: "Back in Stock",
        status: "READY",
        icon: PackageCheck,
        activeClass:
            "border-emerald-500 bg-emerald-50 text-emerald-700 hover:bg-emerald-100",
        appliesTo: ["almost_out", "out_of_stock"],
    },
];

const normalizeStatus = (status) => {
    if (typeof status !== "string") {
        return "";
    }

    const value = status.toLowerCase();

    if (value === "ready") {
        return "in_stock";
    }

    if (value === "out") {
        return "out_of_stock";
    }

    if (value === "almostout") {
        return "almost_out";
    }

    return value;
};

const actionsFor = (status) =>
    ACTIONS.filter((action) => action.appliesTo.includes(normalizeStatus(status)));

const getStatusMeta = (status) => {
    const key = normalizeStatus(status);

    if (STATUS_META[key]) {
        return STATUS_META[key];
    }

    return {
        label: status ?? "Unknown",
        badgeClass: "border-border bg-muted text-muted-foreground",
    };
};

export default function StockReport({
    selectedOutlet = null,
    outlets = [],
    stockItems: initialStockItems = [],
    todayDate = new Date().toISOString().split("T")[0],
}) {
    const [currentTime, setCurrentTime] = useState(() => new Date());
    const [stockItems, setStockItems] = useState(() =>
        initialStockItems.map((item) => ({
            ...item,
            action: null,
        })),
    );

    const initialOutletId =
        selectedOutlet?.id !== undefined
            ? String(selectedOutlet.id)
            : outlets.length > 0
              ? String(outlets[0].id)
              : "";

    const [formData, setFormData] = useState(() => ({
        outletId: initialOutletId,
        reporterId: "",
        date: todayDate ?? new Date().toISOString().split("T")[0],
    }));

    const [reporters, setReporters] = useState([]);
    const [isLoadingSchedule, setIsLoadingSchedule] = useState(false);
    const [scheduleStatus, setScheduleStatus] = useState("idle");
    const [scheduleMessage, setScheduleMessage] = useState(
        "Select an outlet to load today's shift schedule.",
    );

    // Table loading state
    const tableRef = useRef(null);

    // Button animation states
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submitSuccess, setSubmitSuccess] = useState(false);
    const buttonRef = useRef(null);
    const iconRef = useRef(null);

    // Entrance animation refs
    const navRef = useRef(null);
    const headerCardRef = useRef(null);
    const stockCardRef = useRef(null);
    const summaryCardRef = useRef(null);

    // Navbar shadow on scroll
    const [isScrolled, setIsScrolled] = useState(false);

    useEffect(() => {
        const handleScroll = () => {
            setIsScrolled(window.scrollY > 10);
        };

        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    useEffect(() => {
        setStockItems(
            initialStockItems.map((item) => ({
                ...item,
                action: null,
            })),
        );
    }, [initialStockItems]);

    // Entrance animations
    useEffect(() => {
        const ctx = gsap.context(() => {
            const tl = gsap.timeline({ defaults: { ease: "power2.out" } });

            tl.fromTo(
                navRef.current,
                { y: -10, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.4 }
            )
            .fromTo(
                headerCardRef.current,
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.5 },
                "-=0.2"
            )
            .fromTo(
                stockCardRef.current,
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.5 },
                "-=0.3"
            )
            .fromTo(
                summaryCardRef.current,
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.5 },
                "-=0.3"
            );
        });

        return () => ctx.revert();
    }, []);

    // Animate table rows only on initial load or when outlet changes
    useEffect(() => {
        if (tableRef.current && initialStockItems.length > 0) {
            const rows = tableRef.current.querySelectorAll('tbody tr');
            gsap.fromTo(
                rows,
                { opacity: 0, y: 20 },
                {
                    opacity: 1,
                    y: 0,
                    duration: 0.5,
                    stagger: 0.06,
                    ease: 'power2.out',
                }
            );
        }
    }, [initialStockItems]);

    useEffect(() => {
        if (selectedOutlet?.id !== undefined) {
            const outletId = String(selectedOutlet.id);
            setFormData((prev) =>
                prev.outletId === outletId ? prev : { ...prev, outletId },
            );
        }
    }, [selectedOutlet?.id]);

    useEffect(() => {
        const timer = setInterval(() => {
            setCurrentTime(new Date());
        }, 1000);

        return () => clearInterval(timer);
    }, []);

    const formatTime = (date) =>
        date.toLocaleTimeString("en-US", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
            hour12: false,
        });

    const fetchSchedules = useCallback(
        async (outletId) => {
            if (!outletId) {
                setReporters([]);
                setFormData((prev) => ({ ...prev, reporterId: "" }));
                setScheduleStatus("idle");
                setScheduleMessage(
                    "Select an outlet to load today's shift schedule.",
                );
                return;
            }

            setIsLoadingSchedule(true);
            setScheduleStatus("loading");
            setScheduleMessage("Loading shift schedule");
            setReporters([]);
            setFormData((prev) => ({ ...prev, reporterId: "" }));

            try {
                const response = await axios.get(
                    `/api/outlets/${outletId}/schedules/today`,
                );

                const staffOptions = (response.data?.staff ?? []).map(
                    (staff) => ({
                        id: String(staff.id),
                        name: staff.name,
                    }),
                );

                setReporters(staffOptions);

                const status =
                    response.data?.status ??
                    (staffOptions.length ? "ok" : "empty");
                setScheduleStatus(status);

                setScheduleMessage(
                    response.data?.message ??
                        (staffOptions.length
                            ? ""
                            : "No scheduled baristas for today at this outlet."),
                );

                setFormData((prev) => ({
                    ...prev,
                    reporterId:
                        staffOptions.length === 1 ? staffOptions[0].id : "",
                }));
            } catch (error) {
                const message =
                    error?.response?.data?.message ??
                    "Failed to load shift schedule.";

                setReporters([]);
                setFormData((prev) => ({ ...prev, reporterId: "" }));
                setScheduleStatus("error");
                setScheduleMessage(message);
                console.error("Schedule lookup error:", error);
            } finally {
                setIsLoadingSchedule(false);
            }
        },
        [],
    );

    useEffect(() => {
        fetchSchedules(formData.outletId);
    }, [formData.outletId, fetchSchedules]);

    const handleReporterChange = (event) => {
        setFormData((prev) => ({
            ...prev,
            reporterId: event.target.value,
        }));
    };

    const handleActionClick = (itemId, action) => {
        setStockItems((items) =>
            items.map((item) => {
                if (item.id === itemId) {
                    return {
                        ...item,
                        action: item.action === action ? null : action,
                    };
                }
                return item;
            }),
        );
    };

    const outletName = useMemo(() => {
        const option = outlets.find(
            (outlet) => String(outlet.id) === String(formData.outletId),
        );
        return option?.name ?? "";
    }, [formData.outletId, outlets]);

    const selectedReporter = useMemo(
        () =>
            reporters.find(
                (staff) => String(staff.id) === String(formData.reporterId),
            ),
        [reporters, formData.reporterId],
    );

    const readableDate = useMemo(() => {
        const nextDate = new Date(formData.date);

        if (Number.isNaN(nextDate.getTime())) {
            return formData.date;
        }

        return nextDate.toLocaleDateString("en-US", {
            weekday: "short",
            year: "numeric",
            month: "short",
            day: "numeric",
        });
    }, [formData.date]);

    const handleReport = async () => {
        if (!formData.outletId) {
            toast.error("Select an outlet first.");
            return;
        }

        if (!formData.reporterId) {
            toast.error("Select the on-duty barista.");
            return;
        }

        // Report exactly what the barista marked. Items were previously
        // re-sent on every submission just for already being low, which filled
        // the manager's queue with rows nobody had touched.
        const itemsToReport = stockItems
            .map((item) => {
                const action = ACTIONS.find(
                    (candidate) => candidate.label === item.action,
                );

                if (!action) {
                    return null;
                }

                return {
                    item_id: item.id,
                    status: action.status,
                    action: action.label,
                };
            })
            .filter(Boolean);

        // Check if there are any items to report
        if (itemsToReport.length === 0) {
            toast.error("Mark at least one item before submitting.");
            return;
        }

        // Prepare the payload
        const payload = {
            outlet_id: parseInt(formData.outletId),
            user_id: parseInt(formData.reporterId),
            items: itemsToReport,
        };

        // Start loading animation
        setIsSubmitting(true);
        setSubmitSuccess(false);
        
        // Animate button to loading state
        if (buttonRef.current && iconRef.current) {
            gsap.to(buttonRef.current, {
                scale: 0.95,
                duration: 0.2,
                ease: "power2.inOut"
            });
        }

        try {
            const response = await axios.post("/api/reports", payload);

            if (response.data.success) {
                // Success animation
                setSubmitSuccess(true);
                
                if (buttonRef.current && iconRef.current) {
                    // Button scale and color transition
                    gsap.to(buttonRef.current, {
                        scale: 1.05,
                        duration: 0.3,
                        ease: "back.out(1.7)"
                    });
                    
                    // Icon morph animation
                    gsap.to(iconRef.current, {
                        scale: 0,
                        rotation: -90,
                        duration: 0.2,
                        ease: "power2.in",
                        onComplete: () => {
                            gsap.fromTo(iconRef.current,
                                { scale: 0, rotation: 90 },
                                { 
                                    scale: 1, 
                                    rotation: 0,
                                    duration: 0.4,
                                    ease: "elastic.out(1, 0.5)"
                                }
                            );
                        }
                    });
                }
                
                const created = response.data.data?.total_items ?? 0;
                toast.success(
                    created > 0
                        ? `${response.data.message} (${created} item${created === 1 ? "" : "s"} reported)`
                        : response.data.message,
                );

                // Clear the marks so the next round starts from a clean slate.
                setStockItems((items) =>
                    items.map((item) => ({ ...item, action: null })),
                );
                
                // Reset button state after delay
                setTimeout(() => {
                    setIsSubmitting(false);
                    setSubmitSuccess(false);
                    if (buttonRef.current) {
                        gsap.to(buttonRef.current, {
                            scale: 1,
                            duration: 0.3,
                            ease: "power2.out"
                        });
                    }
                }, 2000);
            }
        } catch (error) {
            // Error state - reset immediately
            setIsSubmitting(false);
            setSubmitSuccess(false);
            
            if (buttonRef.current) {
                gsap.to(buttonRef.current, {
                    scale: 1,
                    duration: 0.2,
                    ease: "power2.out"
                });
            }
            
            const errorMessage =
                error.response?.data?.message ||
                "Failed to submit report. Please try again.";
            toast.error(errorMessage);
            console.error("Report submission error:", error);
        }
    };

    const greetingLabel = outletName || "Team";
    const itemLabel = stockItems.length === 1 ? "item" : "items";
    const tableSummary = `Showing ${stockItems.length} ${itemLabel} for ${outletName || "your outlet"}.`;

    // Check if at least one item has been marked for reporting
    const hasMarkedItems = stockItems.some(item => item.action !== null);

    const canSubmit =
        Boolean(formData.outletId) &&
        Boolean(formData.reporterId) &&
        reporters.length > 0 &&
        !isLoadingSchedule &&
        hasMarkedItems;
    const singleReporterAutoSelected =
        reporters.length === 1 && Boolean(formData.reporterId);

    let scheduleMessageClass = "text-xs text-muted-foreground";
    if (scheduleStatus === "error") {
        scheduleMessageClass = "text-xs text-red-600";
    } else if (scheduleStatus === "empty") {
        scheduleMessageClass = "text-xs text-amber-600";
    } else if (scheduleStatus === "ok") {
        scheduleMessageClass = "text-xs text-emerald-600";
    } else if (scheduleStatus === "fallback") {
        scheduleMessageClass = "text-xs text-amber-600 font-medium";
    } else if (scheduleStatus === "loading") {
        scheduleMessageClass = "text-xs text-muted-foreground";
    }

    return (
        <>
            <Head
                title={
                    outletName ? `${outletName} Stock Report` : "Stock Report"
                }
            />

            {/* Navigation Bar */}
            <nav ref={navRef} className={cn(
                "bg-white border-b border-gray-200 sticky top-0 z-50 transition-shadow duration-300",
                isScrolled ? "shadow-md" : "shadow-none"
            )}>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between items-center h-14">
                        {/* Logo/Brand */}
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-8 bg-gray-900 rounded-lg flex items-center justify-center">
                                <span className="text-white text-lg font-bold">S</span>
                            </div>
                            <div>
                                <h1 className="text-base font-bold text-gray-900">Stock Report System</h1>
                                <p className="text-xs text-gray-500">Stock Management</p>
                            </div>
                        </div>

                        {/* Navigation */}
                        <div className="flex items-center gap-3">
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="gap-2"
                            >
                                <Link href="/">
                                    <Home className="h-4 w-4" />
                                    Back to Home
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </nav>

            <div className="min-h-screen bg-muted/20 py-8 sm:py-10">
                <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                    <Card ref={headerCardRef} className={cn(
                        "sticky top-14 z-40 bg-white transition-shadow duration-300",
                        isScrolled ? "shadow-md" : "shadow-none"
                    )}>
                        <CardHeader className="pb-6">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div className="flex flex-col gap-2">
                                    <CardTitle className="text-2xl font-bold tracking-tight sm:text-3xl">
                                        {greetingLabel}
                                    </CardTitle>
                                    <CardDescription className="text-sm">
                                        Stock Report Dashboard • {readableDate}
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant="secondary"
                                        className="flex items-center gap-2 px-3 py-1.5 text-sm font-medium"
                                    >
                                        <Clock3 className="h-4 w-4" />
                                        {formatTime(currentTime)}
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="border-t pt-4">
                            <div className="space-y-2">
                                <Label className="text-sm font-medium text-muted-foreground">
                                    Scheduled Baristas Today
                                    {isLoadingSchedule && (
                                        <Loader2 className="ml-2 inline h-3.5 w-3.5 animate-spin" />
                                    )}
                                </Label>
                                <div className="min-h-[56px] rounded-lg border bg-muted/40 p-3">
                                    {reporters.length > 0 ? (
                                        <div className="flex flex-wrap gap-2">
                                            {reporters.map((staff) => (
                                                <span
                                                    key={staff.id}
                                                    className="inline-flex items-center rounded-md border bg-background px-2.5 py-1 text-xs font-medium"
                                                >
                                                    {staff.name}
                                                </span>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            No scheduled baristas for today
                                        </p>
                                    )}
                                </div>
                                {scheduleMessage && (
                                    <p className={cn("text-xs mt-1", scheduleMessageClass)}>
                                        {scheduleMessage}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card ref={stockCardRef} className="shadow-sm">
                        <CardHeader className="border-b bg-muted/30">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        Stock Overview
                                        <Badge variant="secondary" className="font-mono text-xs">
                                            {stockItems.length} {itemLabel}
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription className="mt-1.5">
                                        Mark items that ran low, ran out, or have just been restocked
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="px-6 pt-4 pb-2">
                                <div className="rounded-lg border bg-blue-50/50 border-blue-200 px-4 py-3">
                                    <p className="text-sm text-blue-900">
                                        <strong>How to submit:</strong> Mark every item whose status changed — running low, out of stock, or back in stock after a restock — then pick the reporting barista below and click Submit Report.
                                    </p>
                                </div>
                            </div>
                            <div className="overflow-x-auto px-6">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead className="font-semibold">
                                                Item
                                            </TableHead>
                                            <TableHead className="font-semibold">
                                                Current Status
                                            </TableHead>
                                            <TableHead className="min-w-[240px] font-semibold">
                                                Action
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody ref={tableRef}>
                                        {stockItems.length === 0 ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={3}
                                                    className="py-16 text-center"
                                                >
                                                    <div className="flex flex-col items-center gap-3">
                                                        <div className="rounded-full bg-muted p-4">
                                                            <Ban className="h-8 w-8 text-muted-foreground" />
                                                        </div>
                                                        <div>
                                                            <p className="font-medium text-foreground">
                                                                No stock items
                                                                available
                                                            </p>
                                                            <p className="text-sm text-muted-foreground">
                                                                There are no
                                                                items to display
                                                                for this outlet.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            stockItems.map((item) => {
                                                const statusMeta =
                                                    getStatusMeta(item.status);
                                                const availableActions =
                                                    actionsFor(item.status);

                                                return (
                                                    <TableRow
                                                        key={item.id}
                                                        className="border-b transition-colors hover:bg-muted/30"
                                                    >
                                                        <TableCell className="py-4 font-medium">
                                                            {item.name}
                                                        </TableCell>
                                                        <TableCell className="py-4">
                                                            <Badge
                                                                variant="outline"
                                                                className={cn(
                                                                    "text-xs font-medium",
                                                                    statusMeta.badgeClass,
                                                                )}
                                                            >
                                                                {statusMeta.label}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="py-4">
                                                            <div className="flex flex-wrap gap-2">
                                                                {availableActions.map(
                                                                    (action) => {
                                                                        const Icon =
                                                                            action.icon;
                                                                        const isActive =
                                                                            item.action ===
                                                                            action.label;

                                                                        return (
                                                                            <Button
                                                                                key={
                                                                                    action.label
                                                                                }
                                                                                type="button"
                                                                                variant="outline"
                                                                                size="sm"
                                                                                aria-pressed={
                                                                                    isActive
                                                                                }
                                                                                onClick={() =>
                                                                                    handleActionClick(
                                                                                        item.id,
                                                                                        action.label,
                                                                                    )
                                                                                }
                                                                                className={cn(
                                                                                    "flex items-center gap-1.5 font-medium transition-all",
                                                                                    isActive
                                                                                        ? action.activeClass
                                                                                        : INACTIVE_ACTION_CLASS,
                                                                                )}
                                                                            >
                                                                                <Icon
                                                                                    className="h-4 w-4"
                                                                                    aria-hidden="true"
                                                                                />
                                                                                <span className="whitespace-nowrap">
                                                                                    {
                                                                                        action.label
                                                                                    }
                                                                                </span>
                                                                            </Button>
                                                                        );
                                                                    },
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            })
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card ref={summaryCardRef} className="shadow-sm">
                        <CardHeader className="border-b bg-muted/30 pb-3">
                            <CardTitle>Complete Your Report</CardTitle>
                            <CardDescription>
                                Select the barista submitting this report
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-6">
                            <div className="space-y-2">
                                <Label
                                    htmlFor="reporter"
                                    className="text-sm font-medium"
                                >
                                    Who is reporting?
                                </Label>
                                <select
                                    id="reporter"
                                    value={formData.reporterId}
                                    onChange={handleReporterChange}
                                    disabled={
                                        isLoadingSchedule ||
                                        reporters.length === 0 ||
                                        singleReporterAutoSelected
                                    }
                                    className="h-11 w-full rounded-lg border-2 border-input bg-background px-4 text-sm font-medium shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {reporters.length === 0 ? (
                                        <option value="">
                                            No baristas available
                                        </option>
                                    ) : (
                                        <>
                                            {reporters.length > 1 && (
                                                <option value="">
                                                    Choose a barista
                                                </option>
                                            )}
                                            {reporters.map((staff) => (
                                                <option
                                                    key={staff.id}
                                                    value={staff.id}
                                                >
                                                    {staff.name}
                                                </option>
                                            ))}
                                        </>
                                    )}
                                </select>
                            </div>

                            <div className="flex justify-end pt-4 border-t">
                                <Button
                                    ref={buttonRef}
                                    size="lg"
                                    onClick={handleReport}
                                    disabled={!canSubmit || isSubmitting}
                                    className={cn(
                                        "px-8 font-semibold shadow-md hover:shadow-lg transition-all duration-300 relative overflow-hidden",
                                        submitSuccess && "bg-emerald-600 hover:bg-emerald-600"
                                    )}
                                >
                                    <span
                                        className={cn(
                                            "flex items-center gap-2 transition-opacity duration-200",
                                            isSubmitting && "opacity-0"
                                        )}
                                    >
                                        Submit Report
                                    </span>

                                    {isSubmitting && (
                                        <span
                                            ref={iconRef}
                                            className="absolute inset-0 flex items-center justify-center"
                                        >
                                            {!submitSuccess ? (
                                                <Loader2 className="h-5 w-5 animate-spin" />
                                            ) : (
                                                <Check className="h-5 w-5" />
                                            )}
                                        </span>
                                    )}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
