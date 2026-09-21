import AuthenticatedLayout from "@/Shared/Layouts/AuthenticatedLayout";
import { Head, Link, router } from "@inertiajs/react";
import { useMemo, useState, useEffect, useRef } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { AlertTriangle, Boxes, PackageX, Tag, Plus } from "lucide-react";
import ItemFormDialog from "@/Features/Inventory/Components/ItemFormDialog";
import DeleteConfirmDialog from "@/Features/Inventory/Components/DeleteConfirmDialog";
import { toast } from "sonner";
import gsap from "gsap";
import CountUp from "react-countup";
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from "@/Components/ui/pagination";

const STATUS_META = {
    in_stock: {
        label: "In Stock",
        tone: "bg-green-50 text-green-700 border border-green-100",
    },
    almost_out: {
        label: "Almost Out",
        tone: "bg-amber-50 text-amber-700 border border-amber-100",
    },
    out_of_stock: {
        label: "Out of Stock",
        tone: "bg-rose-50 text-rose-700 border border-rose-100",
    },
};

const getStatusMeta = (status) => {
    if (!status) {
        return STATUS_META.in_stock;
    }

    const normalized = status.toString().toLowerCase();
    if (STATUS_META[normalized]) {
        return STATUS_META[normalized];
    }

    if (normalized === "ready") {
        return STATUS_META.in_stock;
    }

    if (normalized === "out") {
        return STATUS_META.out_of_stock;
    }

    if (normalized === "almostout") {
        return STATUS_META.almost_out;
    }

    return STATUS_META.in_stock;
};

export default function Dashboard({
    items = {},
    kategoris = [],
    outlets = [],
    stockSummary = {},
    flash,
    filters = {},
}) {
    const itemsData = items?.data ?? [];
    const paginationLinks = items?.links ?? [];
    const itemsMeta = items?.meta ?? {};

    const [searchTerm, setSearchTerm] = useState(filters.search ?? "");
    const [selectedCategory, setSelectedCategory] = useState(
        filters.category ? String(filters.category) : ""
    );
    const [selectedOutlet, setSelectedOutlet] = useState(
        filters.outlet ? String(filters.outlet) : ""
    );

    useEffect(() => {
        setSearchTerm(filters.search ?? "");
        setSelectedCategory(filters.category ? String(filters.category) : "");
        setSelectedOutlet(filters.outlet ? String(filters.outlet) : "");
    }, [filters.search, filters.category, filters.outlet]);

    // Dialog states
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [selectedItem, setSelectedItem] = useState(null);

    // Animation ref
    const tableRef = useRef(null);

    // Show toast notifications for flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, {
                duration: 4000,
            });
        }
        if (flash?.error) {
            toast.error(flash.error, {
                duration: 4000,
            });
        }
    }, [flash]);

    // Animate table rows when loaded
    useEffect(() => {
        if (tableRef.current && itemsData.length > 0) {
            const rows = tableRef.current.querySelectorAll("tbody tr");
            gsap.fromTo(
                rows,
                { opacity: 0, y: 20 },
                {
                    opacity: 1,
                    y: 0,
                    duration: 0.5,
                    stagger: 0.05,
                    ease: "power2.out",
                }
            );
        }
    }, [itemsData]);

    const {
        totalItems = itemsMeta.total ?? itemsData.length,
        categoryCount = kategoris.length,
        outletCount = outlets.length,
        almostOut = 0,
        outOfStock = 0,
    } = stockSummary;

    const actualOutletCount = outlets.length;

    const stats = useMemo(
        () => [
            {
                label: "Total Items",
                value: totalItems,
                helper: `${outletCount} outlet${outletCount === 1 ? "" : "s"}`,
                bgColor: "bg-blue-50",
                textColor: "text-blue-600",
                iconBg: "bg-blue-100",
                borderColor: "border-blue-200",
                Icon: Boxes,
                type: "info",
            },
            {
                label: "Categories",
                value: categoryCount,
                helper: "Organised product groups",
                bgColor: "bg-purple-50",
                textColor: "text-purple-600",
                iconBg: "bg-purple-100",
                borderColor: "border-purple-200",
                Icon: Tag,
                type: "info",
            },
            {
                label: "Almost Out",
                value: almostOut,
                helper: "Items flagged for follow-up",
                bgColor: "bg-amber-50",
                textColor: "text-amber-600",
                iconBg: "bg-amber-100",
                borderColor: "border-amber-300",
                Icon: AlertTriangle,
                type: "warning",
            },
            {
                label: "Out of Stock",
                value: outOfStock,
                helper: "Requires immediate restock",
                bgColor: "bg-rose-50",
                textColor: "text-rose-600",
                iconBg: "bg-rose-100",
                borderColor: "border-rose-300",
                Icon: PackageX,
                type: "alert",
            },
        ],
        [almostOut, categoryCount, outOfStock, outletCount, totalItems]
    );

    const hasActiveFilters = Boolean(
        searchTerm || selectedCategory || selectedOutlet
    );
    const displayedItems = itemsData;

    const applyFilters = (overrides = {}) => {
        const payload = {};

        const searchValue = overrides.search ?? searchTerm;
        if (searchValue) {
            payload.search = searchValue;
        }

        const categoryValue = overrides.category ?? selectedCategory;
        if (categoryValue) {
            payload.category = categoryValue;
        }

        const outletValue = overrides.outlet ?? selectedOutlet;
        if (outletValue) {
            payload.outlet = outletValue;
        }

        if (overrides.page) {
            payload.page = overrides.page;
        }

        router.get(route("dashboard"), payload, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    useEffect(() => {
        const handler = setTimeout(() => {
            const canonicalSearch = filters.search ?? "";
            if ((searchTerm || "") === canonicalSearch) {
                return;
            }

            applyFilters({ search: searchTerm, page: 1 });
        }, 400);

        return () => clearTimeout(handler);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchTerm, filters.search]);

    const handleResetFilters = () => {
        setSearchTerm("");
        setSelectedCategory("");
        setSelectedOutlet("");
        applyFilters({ search: "", category: "", outlet: "", page: 1 });
    };

    const handleEdit = (item) => {
        setSelectedItem(item);
        setEditDialogOpen(true);
    };

    const handleDelete = (item) => {
        setSelectedItem(item);
        setDeleteDialogOpen(true);
    };

    const handleCategoryChange = (event) => {
        const value = event.target.value;
        setSelectedCategory(value);
        applyFilters({ category: value, page: 1 });
    };

    const handleOutletChange = (event) => {
        const value = event.target.value;
        setSelectedOutlet(value);
        applyFilters({ outlet: value, page: 1 });
    };

    const LaravelPagination = ({ links = [] }) => {
        if (!links.length) return null;

        // Filter out prev/next and get page links
        const prevLink = links[0];
        const nextLink = links[links.length - 1];
        const pageLinks = links.slice(1, -1);

        return (
            <Pagination className="mt-6">
                <PaginationContent>
                    <PaginationItem>
                        <PaginationPrevious
                            href={prevLink?.url || "#"}
                            className={
                                !prevLink?.url
                                    ? "pointer-events-none opacity-50"
                                    : ""
                            }
                            preserveScroll
                            preserveState
                        />
                    </PaginationItem>

                    {pageLinks.map((link, index) => {
                        // Check if this is an ellipsis (Laravel uses "...")
                        if (
                            link.label === "..." ||
                            link.label.includes("...")
                        ) {
                            return (
                                <PaginationItem key={`ellipsis-${index}`}>
                                    <PaginationEllipsis />
                                </PaginationItem>
                            );
                        }

                        return (
                            <PaginationItem key={link.label || index}>
                                <PaginationLink
                                    href={link.url || "#"}
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
                            href={nextLink?.url || "#"}
                            className={
                                !nextLink?.url
                                    ? "pointer-events-none opacity-50"
                                    : ""
                            }
                            preserveScroll
                            preserveState
                        />
                    </PaginationItem>
                </PaginationContent>
            </Pagination>
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Quick Stats */}
                    <div className="grid grid-cols-1 gap-4 mb-8 sm:grid-cols-2 lg:grid-cols-4">
                        {stats.map(
                            ({
                                label,
                                value,
                                helper,
                                bgColor,
                                textColor,
                                iconBg,
                                borderColor,
                                Icon,
                                type,
                            }) => (
                                <Card
                                    key={label}
                                    className={`relative overflow-hidden border-2 shadow-sm transition-all hover:shadow-md ${borderColor} ${bgColor}`}
                                >
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <CardTitle className="text-sm font-medium text-gray-600 mb-3">
                                                    {label}
                                                </CardTitle>
                                                <div
                                                    className={`text-3xl font-bold ${textColor}`}
                                                >
                                                    <CountUp
                                                        end={value}
                                                        duration={1.5}
                                                        separator=","
                                                    />
                                                </div>
                                            </div>
                                            <div
                                                className={`rounded-xl ${iconBg} p-3`}
                                            >
                                                <Icon
                                                    className={`h-6 w-6 ${textColor}`}
                                                    aria-hidden="true"
                                                />
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="pt-0">
                                        <p
                                            className={`text-sm font-medium ${textColor.replace(
                                                "600",
                                                "700"
                                            )}`}
                                        >
                                            {helper}
                                        </p>
                                    </CardContent>
                                    {type === "warning" && value > 0 && (
                                        <div className="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-400 to-amber-500"></div>
                                    )}
                                    {type === "alert" && value > 0 && (
                                        <div className="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-400 to-rose-500"></div>
                                    )}
                                </Card>
                            )
                        )}
                    </div>

                    {/* Item Management Section */}
                    <Card className="border border-gray-100 shadow-sm">
                        <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-xl font-semibold text-gray-900">
                                    Inventory Overview
                                </CardTitle>
                                <p className="text-sm text-gray-500">
                                    Search, filter, and manage items across your
                                    outlets.
                                </p>
                            </div>
                            <Button asChild>
                                <button
                                    onClick={() => setCreateDialogOpen(true)}
                                    className="inline-flex items-center gap-2"
                                >
                                    <Plus className="w-4 h-4" />
                                    Add Item
                                </button>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Search and Filter */}
                            <div className="rounded-lg border border-gray-200 bg-gray-50/50 p-4">
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <div className="flex flex-col gap-2 lg:col-span-2">
                                        <label className="text-sm font-medium text-gray-700">
                                            Search Items
                                        </label>
                                        <Input
                                            type="text"
                                            placeholder="Type item name..."
                                            value={searchTerm}
                                            onChange={(e) =>
                                                setSearchTerm(e.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <label className="text-sm font-medium text-gray-700">
                                            Filter by Category
                                        </label>
                                        <select
                                            value={selectedCategory}
                                            onChange={handleCategoryChange}
                                            className="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                        >
                                            <option value="">
                                                All Categories
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
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <label className="text-sm font-medium text-gray-700">
                                            Filter by Outlet
                                        </label>
                                        <select
                                            value={selectedOutlet}
                                            onChange={handleOutletChange}
                                            className="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                        >
                                            <option value="">
                                                All Outlets
                                            </option>
                                            {outlets.map((outlet) => (
                                                <option
                                                    key={outlet.id}
                                                    value={outlet.id}
                                                >
                                                    {outlet.nama}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                {hasActiveFilters && (
                                    <div className="mt-4 flex items-center justify-between border-t border-gray-200 pt-4">
                                        <p className="text-sm text-gray-600">
                                            Showing{" "}
                                            <span className="font-semibold text-gray-900">
                                                {displayedItems.length}
                                            </span>{" "}
                                            of{" "}
                                            <span className="font-semibold text-gray-900">
                                                {itemsMeta.total ??
                                                    displayedItems.length}
                                            </span>{" "}
                                            items
                                        </p>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleResetFilters}
                                        >
                                            Clear Filters
                                        </Button>
                                    </div>
                                )}
                            </div>

                            {/* Items Table */}
                            {displayedItems.length > 0 ? (
                                <div className="overflow-x-auto rounded-lg border border-gray-200">
                                    <table
                                        ref={tableRef}
                                        className="w-full text-left"
                                    >
                                        <thead className="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-gray-600">
                                            <tr>
                                                <th className="px-6 py-3.5">
                                                    Item Name
                                                </th>
                                                <th className="px-6 py-3.5">
                                                    Category
                                                </th>
                                                <th className="px-6 py-3.5">
                                                    Status
                                                </th>
                                                <th className="px-6 py-3.5">
                                                    Outlets
                                                </th>
                                                <th className="px-6 py-3.5 text-right">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100 text-sm text-gray-700">
                                            {displayedItems.map((item) => (
                                                <tr
                                                    key={item.id}
                                                    className="hover:bg-gray-50/60"
                                                >
                                                    <td className="px-6 py-4">
                                                        <div className="font-medium text-gray-900">
                                                            {item.nama}
                                                        </div>
                                                        {item.stok !==
                                                            undefined && (
                                                            <p className="text-xs text-gray-500">
                                                                Stock:{" "}
                                                                {item.stok}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <span className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                                            {item.kategori
                                                                ?.nama ??
                                                                "Uncategorised"}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
                                                                item.deleted
                                                                    ? "bg-gray-100 text-gray-600"
                                                                    : "bg-green-50 text-green-700"
                                                            }`}
                                                        >
                                                            {item.deleted
                                                                ? "Archived"
                                                                : "Active"}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <div className="flex flex-wrap gap-2">
                                                            {(item.outlets
                                                                ?.length ?? 0) >
                                                            0 ? (
                                                                (selectedOutlet
                                                                    ? item.outlets.filter(
                                                                          (
                                                                              outlet
                                                                          ) =>
                                                                              String(
                                                                                  outlet.id
                                                                              ) ===
                                                                              selectedOutlet
                                                                      )
                                                                    : item.outlets
                                                                ).map(
                                                                    (
                                                                        outlet
                                                                    ) => {
                                                                        const statusMeta =
                                                                            getStatusMeta(
                                                                                outlet?.current_status
                                                                            );
                                                                        return (
                                                                            <div
                                                                                key={`${item.id}-${outlet.id}`}
                                                                                className={`inline-flex flex-col gap-1 rounded-lg border px-3 py-2 ${statusMeta.tone}`}
                                                                            >
                                                                                <div className="flex items-center gap-1.5">
                                                                                    <span className="text-sm">
                                                                                        {
                                                                                            outlet.icon
                                                                                        }
                                                                                    </span>
                                                                                    <span className="text-xs font-semibold">
                                                                                        {outlet.short_name ??
                                                                                            outlet.nama}
                                                                                    </span>
                                                                                </div>
                                                                                <span className="text-[11px] font-medium">
                                                                                    {
                                                                                        statusMeta.label
                                                                                    }
                                                                                </span>
                                                                            </div>
                                                                        );
                                                                    }
                                                                )
                                                            ) : (
                                                                <span className="text-xs text-gray-400">
                                                                    No outlet
                                                                    assigned
                                                                </span>
                                                            )}
                                                        </div>
                                                        {selectedOutlet &&
                                                            !(
                                                                item.outlets ||
                                                                []
                                                            ).some(
                                                                (outlet) =>
                                                                    String(
                                                                        outlet.id
                                                                    ) ===
                                                                    selectedOutlet
                                                            ) && (
                                                                <p className="mt-2 text-xs text-gray-400">
                                                                    Not assigned
                                                                    to the
                                                                    selected
                                                                    outlet.
                                                                </p>
                                                            )}
                                                    </td>
                                                    <td className="px-6 py-4">
                                                        <div className="flex flex-wrap items-center justify-end gap-2">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleEdit(
                                                                        item
                                                                    )
                                                                }
                                                            >
                                                                Edit
                                                            </Button>
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        item
                                                                    )
                                                                }
                                                            >
                                                                Delete
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="rounded-lg border border-dashed border-gray-300 bg-gray-50 py-12 text-center">
                                    <div className="mx-auto max-w-sm">
                                        <p className="text-base font-medium text-gray-700">
                                            Nothing to show here yet.
                                        </p>
                                        <p className="mt-2 text-sm text-gray-500">
                                            {hasActiveFilters
                                                ? "No items match your filters. Try adjusting your search criteria."
                                                : "Start by adding your first item to the catalogue."}
                                        </p>
                                        {!hasActiveFilters && (
                                            <Button
                                                onClick={() =>
                                                    setCreateDialogOpen(true)
                                                }
                                                className="mt-6"
                                            >
                                                <Plus className="mr-2 h-4 w-4" />
                                                Add Item
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            )}

                            {paginationLinks.length > 0 && (
                                <LaravelPagination links={paginationLinks} />
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Dialogs */}
            <ItemFormDialog
                open={createDialogOpen}
                onOpenChange={setCreateDialogOpen}
                kategoris={kategoris}
                outlets={outlets}
                mode="create"
            />

            <ItemFormDialog
                open={editDialogOpen}
                onOpenChange={setEditDialogOpen}
                item={selectedItem}
                kategoris={kategoris}
                outlets={outlets}
                mode="edit"
            />

            <DeleteConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
                item={selectedItem}
            />
        </AuthenticatedLayout>
    );
}
