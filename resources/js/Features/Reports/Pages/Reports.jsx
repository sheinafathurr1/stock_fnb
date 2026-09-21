import AuthenticatedLayout from '@/Shared/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import ResetReportsDialog from '@/Features/Reports/Components/ResetReportsDialog';
import { useMemo, useState, useEffect, useRef, useCallback } from 'react';
import { RotateCcw, FileText, Check, RefreshCw, Bell } from 'lucide-react';
import gsap from 'gsap';
import axios from 'axios';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/Components/ui/pagination';

const STATUS_LABELS = {
    READY: {
        label: 'Restocked',
        tone: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    },
    ALMOST_OUT: {
        label: 'Almost Out',
        tone: 'bg-amber-50 text-amber-700 border-amber-300',
    },
    OUT: {
        label: 'Out of Stock',
        tone: 'bg-rose-50 text-rose-700 border-rose-300',
    },
};

// Format date to readable format (Asia/Jakarta timezone)
const formatDateTime = (dateString) => {
    if (!dateString || dateString === '—') return '—';

    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Asia/Jakarta', // Force Jakarta timezone
        });
    } catch {
        return dateString;
    }
};

export default function Reports({
    reports = {},
    outlets = [],
    filters = {},
    config = {},
    pendingCount: initialPendingCount = 0,
}) {
    const initialReportsData = reports?.data ?? [];
    const paginationLinks = reports?.links ?? [];
    // Laravel serialises the paginator's counters at the top level; reading
    // them from a `meta` object that is never sent made every total collapse
    // to the size of the page on screen.
    const currentPage = reports?.current_page ?? 1;

    // Polling refreshes these, so they are state rather than derived values.
    const [totalReports, setTotalReports] = useState(
        reports?.total ?? initialReportsData.length,
    );

    const today = useMemo(() => new Date().toISOString().split('T')[0], []);

    const [outletFilter, setOutletFilter] = useState(filters.outlet ? String(filters.outlet) : '');
    const [dateFilter, setDateFilter] = useState(filters.date ?? today);
    const [resetDialogOpen, setResetDialogOpen] = useState(false);
    const [reportsData, setReportsData] = useState(initialReportsData);
    const [acceptingIds, setAcceptingIds] = useState(new Set());

    // Periodic AJAX polling state
    const [isPolling, setIsPolling] = useState(false);
    const [pendingCount, setPendingCount] = useState(initialPendingCount);
    const [lastPolledAt, setLastPolledAt] = useState(null);
    const POLLING_INTERVAL = config.pollingInterval || 30000; // From backend config

    // Animation refs
    const tableRef = useRef(null);
    const hasAnimated = useRef(false); // Track if initial animation has played

    const outletOptions = useMemo(
        () => outlets.map((outlet) => ({ id: String(outlet.id), name: outlet.name })),
        [outlets],
    );

    useEffect(() => {
        setOutletFilter(filters.outlet ? String(filters.outlet) : '');
        setDateFilter(filters.date ?? today);
    }, [filters.outlet, filters.date, today]);

    useEffect(() => {
        setReportsData(initialReportsData);
        setTotalReports(reports?.total ?? initialReportsData.length);
    }, [initialReportsData, reports?.total]);

    const applyFilters = (overrides = {}) => {
        const payload = {};

        const outletValue = overrides.outlet ?? outletFilter;
        if (outletValue) {
            payload.outlet = outletValue;
        }

        const dateValue = overrides.date ?? dateFilter;
        if (dateValue) {
            payload.date = dateValue;
        }

        if (overrides.page) {
            payload.page = overrides.page;
        }

        router.get(route('reports.index'), payload, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const handleOutletFilterChange = (event) => {
        const outletId = event.target.value;
        setOutletFilter(outletId);
        applyFilters({ outlet: outletId, page: 1 });
    };

    const handleDateFilterChange = (event) => {
        const dateValue = event.target.value;
        setDateFilter(dateValue);
        applyFilters({ date: dateValue, page: 1 });
    };

    const handleClearFilters = () => {
        setOutletFilter('');
        setDateFilter(today);
        applyFilters({ outlet: '', date: today, page: 1 });
    };

    const handleAcceptReport = async (reportId) => {
        setAcceptingIds((prev) => new Set(prev).add(reportId));

        try {
            const response = await axios.post(route('reports.accept', reportId));

            if (response.data.success) {
                // Update local state to mark report as accepted
                setReportsData((prevReports) =>
                    prevReports.map((report) =>
                        report.id === reportId
                            ? {
                                  ...report,
                                  accepted: true,
                                  acceptedAt: response.data.data.accepted_at,
                              }
                            : report
                    )
                );

                // Update pending count
                setPendingCount((prev) => Math.max(0, prev - 1));
            }
        } catch (error) {
            console.error('Failed to accept report:', error);
            alert(error.response?.data?.message || 'Failed to accept report. Please try again.');
        } finally {
            setAcceptingIds((prev) => {
                const newSet = new Set(prev);
                newSet.delete(reportId);
                return newSet;
            });
        }
    };

    // Periodic AJAX polling function
    const pollReports = useCallback(async () => {
        setIsPolling(true);
        try {
            const params = new URLSearchParams();
            if (outletFilter) params.append('outlet', outletFilter);
            if (dateFilter) params.append('date', dateFilter);
            // Without the page, a refresh used to drop the manager back onto
            // the first page's rows while the pagination still said page N.
            params.append('page', String(currentPage));

            const response = await axios.get(route('reports.poll') + '?' + params.toString());

            if (response.data.success) {
                setReportsData(response.data.data);
                setTotalReports(response.data.meta.total);
                setPendingCount(response.data.meta.pending);
                setLastPolledAt(new Date());
            }
        } catch (error) {
            console.error('Polling failed:', error);
        } finally {
            setIsPolling(false);
        }
    }, [outletFilter, dateFilter, currentPage]);

    // Set up periodic polling
    useEffect(() => {
        // Initial poll
        pollReports();

        // Set up interval
        const intervalId = setInterval(pollReports, POLLING_INTERVAL);

        // Cleanup on unmount or filter change
        return () => clearInterval(intervalId);
    }, [pollReports, POLLING_INTERVAL]);

    // The server counts pending reports across the whole filtered day; the
    // visible page is only a slice of it, so never recount from the rows.
    useEffect(() => {
        setPendingCount(initialPendingCount);
    }, [initialPendingCount]);

    const LaravelPagination = ({ links = [] }) => {
        if (!links.length) return null;

        // Filter out prev/next and get page links
        const prevLink = links[0];
        const nextLink = links[links.length - 1];
        const pageLinks = links.slice(1, -1);

        return (
            <Pagination>
                <PaginationContent>
                    <PaginationItem>
                        <PaginationPrevious
                            href={prevLink?.url || '#'}
                            className={!prevLink?.url ? 'pointer-events-none opacity-50' : ''}
                            preserveScroll
                            preserveState
                        />
                    </PaginationItem>

                    {pageLinks.map((link, index) => {
                        // Check if this is an ellipsis (Laravel uses "...")
                        if (link.label === '...' || link.label.includes('...')) {
                            return (
                                <PaginationItem key={`ellipsis-${index}`}>
                                    <PaginationEllipsis />
                                </PaginationItem>
                            );
                        }

                        return (
                            <PaginationItem key={link.label || index}>
                                <PaginationLink
                                    href={link.url || '#'}
                                    isActive={link.active}
                                    preserveScroll
                                    preserveState
                                >
                                    {link.label}
                                </PaginationLink>
                            </PaginationItem>
                        );
                    })}

                    <PaginationItem>
                        <PaginationNext
                            href={nextLink?.url || '#'}
                            className={!nextLink?.url ? 'pointer-events-none opacity-50' : ''}
                            preserveScroll
                            preserveState
                        />
                    </PaginationItem>
                </PaginationContent>
            </Pagination>
        );
    };

    // Animate table rows only on first page load
    useEffect(() => {
        if (tableRef.current && reportsData.length > 0 && !hasAnimated.current) {
            hasAnimated.current = true;
            const rows = tableRef.current.querySelectorAll('tbody tr');
            gsap.fromTo(
                rows,
                { opacity: 0, y: 20 },
                {
                    opacity: 1,
                    y: 0,
                    duration: 0.5,
                    stagger: 0.04,
                    ease: 'power2.out',
                }
            );
        }
    }, [reportsData]);

    const hasFilters = Boolean(outletFilter || dateFilter !== today);

    return (
        <AuthenticatedLayout>
            <Head title="Reports" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    {/* Filters Section */}
                    <div className="rounded-lg border border-gray-200 bg-gray-50/50 p-4 shadow-sm">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <label htmlFor="report-outlet-filter" className="text-sm font-medium text-gray-700">
                                    Filter by Outlet
                                </label>
                                <select
                                    id="report-outlet-filter"
                                    value={outletFilter}
                                    onChange={handleOutletFilterChange}
                                    className="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="">All Outlets</option>
                                    {outletOptions.map((outlet) => (
                                        <option key={outlet.id} value={outlet.id}>
                                            {outlet.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex flex-col gap-2">
                                <label htmlFor="report-date-filter" className="text-sm font-medium text-gray-700">
                                    Filter by Date
                                </label>
                                <input
                                    id="report-date-filter"
                                    type="date"
                                    value={dateFilter}
                                    onChange={handleDateFilterChange}
                                    className="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                />
                            </div>
                        </div>
                        {hasFilters && (
                            <div className="mt-4 flex items-center justify-between border-t border-gray-200 pt-4">
                                <p className="text-sm text-gray-600">
                                    Showing <span className="font-semibold text-gray-900">{reportsData.length}</span> of <span className="font-semibold text-gray-900">{totalReports}</span> reports
                                </p>
                                <Button variant="outline" size="sm" onClick={handleClearFilters}>
                                    Clear Filters
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Report History Table */}
                    <Card className="border border-gray-200 shadow-sm">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-xl font-semibold text-gray-900">
                                        Report History
                                    </CardTitle>
                                    <CardDescription>
                                        Review submitted stock reports by outlet and status
                                    </CardDescription>
                                </div>
                                {/* Polling Status Indicator */}
                                <div className="flex items-center gap-3">
                                    {pendingCount > 0 && (
                                        <Badge className="bg-amber-100 text-amber-800 border-amber-300 gap-1">
                                            <Bell className="h-3 w-3" />
                                            {pendingCount} pending
                                        </Badge>
                                    )}
                                    <div className="flex items-center gap-2 text-xs text-gray-500">
                                        <RefreshCw className={`h-3 w-3 ${isPolling ? 'animate-spin text-blue-500' : ''}`} />
                                        <span>
                                            {isPolling ? 'Updating...' : `Auto-refresh: ${POLLING_INTERVAL / 1000}s`}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {reportsData.length > 0 ? (
                                <>
                                    <div className="overflow-x-auto rounded-lg border border-gray-200">
                                        <table ref={tableRef} className="w-full text-left">
                                            <thead className="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-gray-600">
                                                <tr>
                                                    <th className="px-6 py-3.5">Submitted At</th>
                                                    <th className="px-6 py-3.5">Outlet</th>
                                                    <th className="px-6 py-3.5">Barista</th>
                                                    <th className="px-6 py-3.5">Item</th>
                                                    <th className="px-6 py-3.5">Status</th>
                                                    <th className="px-6 py-3.5">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100 text-sm text-gray-700">
                                                {reportsData.map((report) => {
                                                    const statusMeta =
                                                        STATUS_LABELS[report.status] ??
                                                        STATUS_LABELS.OUT;

                                                    return (
                                                        <tr key={report.id} className="hover:bg-gray-50/60">
                                                            <td className="px-6 py-4 font-medium text-gray-900">
                                                                {formatDateTime(report.submittedAt)}
                                                            </td>
                                                            <td className="px-6 py-4">{report.outlet}</td>
                                                            <td className="px-6 py-4">{report.reporter}</td>
                                                            <td className="px-6 py-4 font-medium">{report.item}</td>
                                                            <td className="px-6 py-4">
                                                                <Badge
                                                                    variant="outline"
                                                                    className={`text-xs font-medium border ${statusMeta.tone}`}
                                                                >
                                                                    {statusMeta.label}
                                                                </Badge>
                                                            </td>
                                                            <td className="px-6 py-4">
                                                                {report.accepted ? (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="text-xs font-medium border bg-green-50 text-green-700 border-green-200"
                                                                    >
                                                                        <Check className="h-3 w-3 mr-1" />
                                                                        Accepted
                                                                    </Badge>
                                                                ) : (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="default"
                                                                        onClick={() => handleAcceptReport(report.id)}
                                                                        disabled={acceptingIds.has(report.id)}
                                                                        className="text-xs h-8 px-3"
                                                                    >
                                                                        {acceptingIds.has(report.id) ? 'Accepting...' : 'ACCEPT'}
                                                                    </Button>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* Bottom Actions Row */}
                                    <div className="flex flex-col lg:flex-row gap-4 border-t border-gray-200 pt-4 lg:items-center lg:justify-between">
                                        {/* Left: Showing text */}
                                        <p className="text-sm text-gray-600 whitespace-nowrap">
                                            Showing <span className="font-semibold text-gray-900">{reportsData.length}</span> of <span className="font-semibold text-gray-900">{totalReports}</span> reports
                                        </p>

                                        {/* Center: Pagination */}
                                        <div className="flex-1 flex justify-center">
                                            <LaravelPagination links={paginationLinks} />
                                        </div>

                                        {/* Right: Reset button */}
                                        <div className="flex justify-end">
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => setResetDialogOpen(true)}
                                                className="gap-2"
                                            >
                                                <RotateCcw className="h-4 w-4" />
                                                Reset All Reports
                                            </Button>
                                        </div>
                                    </div>
                                </>
                            ) : (
                                <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 py-16 text-center">
                                    <div className="mx-auto max-w-sm">
                                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                                            <FileText className="h-8 w-8 text-gray-400" />
                                        </div>
                                        <p className="text-base font-medium text-gray-700">No reports found</p>
                                        <p className="mt-2 text-sm text-gray-500">
                                            {hasFilters
                                                ? 'No reports match your current filters. Try adjusting your search criteria.'
                                                : 'No stock reports have been submitted yet. Reports will appear here once baristas submit them.'}
                                        </p>
                                        {hasFilters && (
                                            <Button onClick={handleClearFilters} variant="outline" className="mt-6">
                                                Clear Filters
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <ResetReportsDialog
                open={resetDialogOpen}
                onOpenChange={setResetDialogOpen}
                reportCount={totalReports}
                outletId={outletFilter}
                outletName={
                    outletOptions.find((outlet) => outlet.id === outletFilter)
                        ?.name ?? ''
                }
                date={dateFilter}
            />
        </AuthenticatedLayout>
    );
}
