import AuthenticatedLayout from "@/Shared/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import React, { useEffect, useMemo, useRef, useState } from "react";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Checkbox } from "@/Components/ui/checkbox";
// import {
//     Collapsible,
//     CollapsibleContent,
//     CollapsibleTrigger,
// } from "@/Components/ui/collapsible";
import { ArrowLeft, Save, Package, Tag, Store } from "lucide-react";
import gsap from "gsap";

const STATUS_OPTIONS = [
    { value: "in_stock", label: "In Stock" },
    { value: "almost_out", label: "Almost Out" },
    { value: "out_of_stock", label: "Out of Stock" },
];

const normalizeStatusValue = (status) => {
    if (!status) {
        return "in_stock";
    }

    const value = status.toString().toLowerCase();

    if (value === "ready") {
        return "in_stock";
    }

    if (value === "out") {
        return "out_of_stock";
    }

    if (value === "almostout") {
        return "almost_out";
    }

    return ["in_stock", "almost_out", "out_of_stock"].includes(value)
        ? value
        : "in_stock";
};

const buildOwnershipMap = (outletList = []) =>
    outletList.reduce((accumulator, outlet) => {
        const status =
            outlet?.pivot?.current_status ??
            outlet?.current_status ??
            "in_stock";

        accumulator[outlet.id] = normalizeStatusValue(status);
        return accumulator;
    }, {});

export default function ItemForm({
    item = null,
    kategoris = [],
    outlets = [],
    mode = "create",
}) {
    const cardRef = useRef(null);
    const formRef = useRef(null);
    const outletRefs = useRef([]);

    const { data, setData, post, put, processing, errors } = useForm({
        nama: item?.nama || "",
        kategori_id: item?.kategori_id || "",
        deleted: item?.deleted ?? false,
        outlet_ids: item?.outlets?.map((o) => o.id) || [],
        ownership_statuses: buildOwnershipMap(item?.outlets ?? []),
    });

    // Collapsible state for outlets
    const [outletsOpen, setOutletsOpen] = useState(false);
    const COLLAPSED_COUNT = 4; // change as needed

    const visibleOutlets = useMemo(
        () => (outletsOpen ? outlets : outlets.slice(0, COLLAPSED_COUNT)),
        [outletsOpen, outlets]
    );
    const selectedOutlets = useMemo(() => {
        const selectedIds = new Set(data.outlet_ids);
        return outlets.filter((outlet) => selectedIds.has(outlet.id));
    }, [data.outlet_ids, outlets]);
    const remainingCount = Math.max(outlets.length - COLLAPSED_COUNT, 0);

    useEffect(() => {
        const ctx = gsap.context(() => {
            if (cardRef.current) {
                gsap.from(cardRef.current, {
                    y: 30,
                    opacity: 0,
                    duration: 0.6,
                    ease: "power3.out",
                });
            }
            if (formRef.current?.children) {
                gsap.from(formRef.current.children, {
                    y: 20,
                    opacity: 0,
                    duration: 0.5,
                    stagger: 0.1,
                    ease: "power2.out",
                    delay: 0.3,
                });
            }
        });
        return () => ctx.revert();
    }, []);

    useEffect(() => {
        if (outletRefs.current.length > 0) {
            gsap.from(outletRefs.current, {
                x: -10,
                opacity: 0,
                duration: 0.4,
                stagger: 0.05,
                ease: "power2.out",
            });
        }
    }, [outletsOpen, outlets]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (mode === "edit" && item) {
            put(`/items/${item.id}`);
        } else {
            post("/items");
        }
    };

    const handleStatusChange = (outletId, status) => {
        setData((current) => ({
            ...current,
            ownership_statuses: {
                ...current.ownership_statuses,
                [outletId]: normalizeStatusValue(status),
            },
        }));
    };

    const handleOutletToggle = (outletId) => {
        setData((current) => {
            const isSelected = current.outlet_ids.includes(outletId);
            const nextOutletIds = isSelected
                ? current.outlet_ids.filter((id) => id !== outletId)
                : [...current.outlet_ids, outletId];

            const nextOwnershipStatuses = { ...current.ownership_statuses };

            if (isSelected) {
                delete nextOwnershipStatuses[outletId];
            } else {
                nextOwnershipStatuses[outletId] =
                    nextOwnershipStatuses[outletId] ?? "in_stock";
            }

            return {
                ...current,
                outlet_ids: nextOutletIds,
                ownership_statuses: nextOwnershipStatuses,
            };
        });

        const checkbox = outletRefs.current.find(
            (el) => el?.dataset?.outletId === String(outletId)
        );
        if (checkbox) {
            gsap.fromTo(
                checkbox,
                { scale: 1 },
                {
                    scale: 1.1,
                    duration: 0.2,
                    yoyo: true,
                    repeat: 1,
                    ease: "power2.inOut",
                }
            );
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-purple-600">
                        <Package className="h-5 w-5 text-white" />
                    </div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {mode === "edit" ? "Edit Item" : "Add New Item"}
                    </h2>
                </div>
            }
        >
            <Head title={mode === "edit" ? "Edit Item" : "Add Item"} />

            <div className="py-8">
                {/* Back Button */}
                <div className="mb-6">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={route("dashboard")}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Link>
                    </Button>
                </div>

                {/* Form Card */}
                <Card
                    ref={cardRef}
                    className="max-w-3xl border-gray-200 shadow-sm"
                >
                    <CardHeader className="space-y-1">
                        <CardTitle className="text-2xl">
                            {mode === "edit"
                                ? `Edit "${item?.nama}"`
                                : "Create New Item"}
                        </CardTitle>
                        <CardDescription>
                            {mode === "edit"
                                ? "Update the item details and outlet assignments below."
                                : "Fill in the details to add a new item to your inventory."}
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-8">
                            <div ref={formRef} className="space-y-6">
                                {/* Item Name */}
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="nama"
                                        className="flex items-center gap-2"
                                    >
                                        <Package className="h-4 w-4 text-gray-500" />
                                        Item Name
                                    </Label>
                                    <Input
                                        type="text"
                                        id="nama"
                                        value={data.nama}
                                        onChange={(e) =>
                                            setData("nama", e.target.value)
                                        }
                                        placeholder="e.g., Cappuccino, Espresso, Croissant..."
                                        className={
                                            errors.nama
                                                ? "border-red-500 focus-visible:ring-red-500"
                                                : ""
                                        }
                                    />
                                    {errors.nama && (
                                        <p className="text-sm text-red-600 flex items-center gap-1">
                                            <span className="font-medium">
                                                ⚠
                                            </span>{" "}
                                            {errors.nama}
                                        </p>
                                    )}
                                </div>

                                {/* Category */}
                                <div className="space-y-2">
                                    <Label
                                        htmlFor="kategori_id"
                                        className="flex items-center gap-2"
                                    >
                                        <Tag className="h-4 w-4 text-gray-500" />
                                        Category
                                    </Label>
                                    <select
                                        id="kategori_id"
                                        value={data.kategori_id}
                                        onChange={(e) =>
                                            setData(
                                                "kategori_id",
                                                e.target.value
                                            )
                                        }
                                        className={`flex h-9 w-full rounded-md border ${
                                            errors.kategori_id
                                                ? "border-red-500"
                                                : "border-input"
                                        } bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-all`}
                                    >
                                        <option value="">
                                            Select a category
                                        </option>
                                        {kategoris.map((kategori) => (
                                            <option
                                                key={kategori.id}
                                                value={kategori.id}
                                            >
                                                {kategori.nama}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.kategori_id && (
                                        <p className="text-sm text-red-600 flex items-center gap-1">
                                            <span className="font-medium">
                                                ⚠
                                            </span>{" "}
                                            {errors.kategori_id}
                                        </p>
                                    )}
                                </div>

                                {/* Outlet Assignment (Collapsible) */}
                                <div className="space-y-3">
                                    <Label className="flex items-center gap-2">
                                        <Store className="h-4 w-4 text-gray-500" />
                                        Assign to Outlets
                                    </Label>
                                    <p className="text-sm text-gray-500">
                                        Select which outlets should carry this
                                        item.
                                    </p>

                                    <div
                                        open={outletsOpen}
                                        onOpenChange={setOutletsOpen}
                                    >
                                        {/* visible list (first N when collapsed; all when open) */}
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {visibleOutlets.map(
                                                (outlet, index) => (
                                                    <div
                                                        key={outlet.id}
                                                        ref={(el) => {
                                                            outletRefs.current[
                                                                index
                                                            ] = el;
                                                        }}
                                                        data-outlet-id={
                                                            outlet.id
                                                        }
                                                        className="flex items-center space-x-3 rounded-lg border border-gray-200 p-4 transition-all hover:border-gray-300 hover:bg-gray-50/50"
                                                    >
                                                        <Checkbox
                                                            id={`outlet-${outlet.id}`}
                                                            checked={data.outlet_ids.includes(
                                                                outlet.id
                                                            )}
                                                            onCheckedChange={() =>
                                                                handleOutletToggle(
                                                                    outlet.id
                                                                )
                                                            }
                                                        />
                                                        <Label
                                                            htmlFor={`outlet-${outlet.id}`}
                                                            className="flex-1 cursor-pointer text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                                        >
                                                            <span className="flex items-center gap-2">
                                                                <span className="text-lg">
                                                                    {
                                                                        outlet.icon
                                                                    }
                                                                </span>
                                                                <span>
                                                                    {
                                                                        outlet.nama
                                                                    }
                                                                </span>
                                                            </span>
                                                        </Label>
                                                    </div>
                                                )
                                            )}
                                        </div>

                                        {/* “+N more / Show less” button */}
                                        {remainingCount > 0 && (
                                            <div className="mt-2">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    {outletsOpen
                                                        ? "Show less"
                                                        : `+${remainingCount} more`}
                                                </Button>
                                            </div>
                                        )}

                                        {/* we render inline above; keep content element for a11y/animation hooks */}
                                    </div>

                                    {mode === "edit" && selectedOutlets.length > 0 && (
                                        <div className="space-y-3">
                                            <Label className="flex items-center gap-2">
                                                <Store className="h-4 w-4 text-gray-500" />
                                                Outlet Availability
                                            </Label>
                                            <p className="text-sm text-gray-500">
                                                Adjust availability for each outlet. These updates change outlet inventory status only.
                                            </p>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                {selectedOutlets.map((outlet) => {
                                                    const status =
                                                        data.ownership_statuses?.[
                                                            outlet.id
                                                        ] ?? "in_stock";

                                                    return (
                                                        <div
                                                            key={outlet.id}
                                                            className="rounded-lg border border-gray-200 p-3 shadow-sm"
                                                        >
                                                            <div className="mb-2 flex items-center justify-between text-sm font-medium text-gray-700">
                                                                <span className="flex items-center gap-2">
                                                                    <span className="text-base">
                                                                        {
                                                                            outlet.icon
                                                                        }
                                                                    </span>
                                                                    {
                                                                        outlet.nama
                                                                    }
                                                                </span>
                                                            </div>
                                                            <select
                                                                value={status}
                                                                onChange={(event) =>
                                                                    handleStatusChange(
                                                                        outlet.id,
                                                                        event
                                                                            .target
                                                                            .value
                                                                    )
                                                                }
                                                                className="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-200"
                                                            >
                                                                {STATUS_OPTIONS.map(
                                                                    ({
                                                                        value,
                                                                        label,
                                                                    }) => (
                                                                        <option
                                                                            key={
                                                                                value
                                                                            }
                                                                            value={
                                                                                value
                                                                            }
                                                                        >
                                                                            {
                                                                                label
                                                                            }
                                                                        </option>
                                                                    )
                                                                )}
                                                            </select>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}

                                    {errors.outlet_ids && (
                                        <p className="text-sm text-red-600 flex items-center gap-1">
                                            <span className="font-medium">
                                                ⚠
                                            </span>{" "}
                                            {errors.outlet_ids}
                                        </p>
                                    )}

                                    {data.outlet_ids.length === 0 && (
                                        <p className="text-sm text-amber-600 flex items-center gap-1 bg-amber-50 px-3 py-2 rounded-md">
                                            <span className="font-medium">
                                                ℹ
                                            </span>
                                            No outlets selected. This item won't
                                            be visible in any outlet.
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex flex-col-reverse gap-3 border-t border-gray-200 pt-6 sm:flex-row sm:justify-end">
                                <Button asChild variant="outline" type="button">
                                    <Link href="/dashboard">Cancel</Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="gap-2"
                                >
                                    {processing ? (
                                        <>
                                            <svg
                                                className="h-4 w-4 animate-spin"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle
                                                    className="opacity-25"
                                                    cx="12"
                                                    cy="12"
                                                    r="10"
                                                    stroke="currentColor"
                                                    strokeWidth="4"
                                                />
                                                <path
                                                    className="opacity-75"
                                                    fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                ></path>
                                            </svg>
                                            Saving...
                                        </>
                                    ) : (
                                        <>
                                            <Save className="h-4 w-4" />
                                            {mode === "edit"
                                                ? "Update Item"
                                                : "Create Item"}
                                        </>
                                    )}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
