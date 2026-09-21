import { useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Save, Package, Tag, Store } from 'lucide-react';
import gsap from 'gsap';
import { toast } from 'sonner';

const STATUS_OPTIONS = [
    { value: 'in_stock', label: 'In Stock' },
    { value: 'almost_out', label: 'Almost Out' },
    { value: 'out_of_stock', label: 'Out of Stock' },
];

const normalizeStatusValue = (status) => {
    if (!status) {
        return 'in_stock';
    }

    const value = status.toString().toLowerCase();

    if (value === 'ready') {
        return 'in_stock';
    }

    if (value === 'out') {
        return 'out_of_stock';
    }

    if (value === 'almostout') {
        return 'almost_out';
    }

    return ['in_stock', 'almost_out', 'out_of_stock'].includes(value)
        ? value
        : 'in_stock';
};

const buildOwnershipMap = (outletList = []) =>
    outletList.reduce((accumulator, outlet) => {
        const status =
            outlet?.pivot?.current_status ??
            outlet?.current_status ??
            'in_stock';

        accumulator[outlet.id] = normalizeStatusValue(status);
        return accumulator;
    }, {});

export default function ItemFormDialog({ 
    open, 
    onOpenChange, 
    item = null, 
    kategoris = [], 
    outlets = [], 
    mode = 'create' 
}) {
    const formRef = useRef(null);
    const outletRefs = useRef([]);
    const initialOwnershipStatuses = useMemo(
        () => buildOwnershipMap(item?.outlets ?? []),
        [item]
    );
    
    const { data, setData, post, put, processing, errors, reset } = useForm({
        nama: item?.nama || '',
        kategori_id: item?.kategori_id || '',
        deleted: item?.deleted ?? false,
        outlet_ids: item?.outlets?.map(outlet => outlet.id) || [],
        ownership_statuses: initialOwnershipStatuses,
    });

    useEffect(() => {
        if (open && formRef.current) {
            // Animate form fields when dialog opens
            gsap.from(formRef.current.children, {
                y: 10,
                opacity: 0,
                duration: 0.3,
                stagger: 0.05,
                ease: 'power2.out',
            });
        }
    }, [open]);

    useEffect(() => {
        // Reset form when item changes
        if (item) {
            setData({
                nama: item.nama || '',
                kategori_id: item.kategori_id || '',
                deleted: item.deleted ?? false,
                outlet_ids: item.outlets?.map(outlet => outlet.id) || [],
                ownership_statuses: buildOwnershipMap(item.outlets ?? []),
            });
        } else {
            reset();
        }
    }, [item]);

    const handleSubmit = (e) => {
        e.preventDefault();

        if (data.outlet_ids.length === 0) {
            toast.error('Assign the item to at least one outlet before saving.', {
                duration: 3500,
            });
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        };

        if (mode === 'edit') {
            put(`/items/${item.id}`, options);
        } else {
            post('/items', options);
        }
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
                    nextOwnershipStatuses[outletId] ?? 'in_stock';
            }

            return {
                ...current,
                outlet_ids: nextOutletIds,
                ownership_statuses: nextOwnershipStatuses,
            };
        });
        
        // Pulse animation on toggle
        const checkbox = outletRefs.current.find(el => el?.dataset?.outletId === String(outletId));
        if (checkbox) {
            gsap.fromTo(checkbox, 
                { scale: 1 },
                { scale: 1.05, duration: 0.15, yoyo: true, repeat: 1, ease: 'power2.inOut' }
            );
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

    const selectedOutlets = useMemo(() => {
        const selectedIds = new Set(data.outlet_ids);
        return outlets.filter((outlet) => selectedIds.has(outlet.id));
    }, [data.outlet_ids, outlets]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-purple-600">
                            <Package className="h-4 w-4 text-white" />
                        </div>
                        {mode === 'edit' ? `Edit "${item?.nama}"` : 'Create New Item'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'edit' 
                            ? 'Update the item details and outlet assignments below.'
                            : 'Fill in the details to add a new item to your inventory.'
                        }
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit}>
                    <div ref={formRef} className="space-y-5 py-4">
                        {/* Item Name */}
                        <div className="space-y-2">
                            <Label htmlFor="nama" className="flex items-center gap-2">
                                <Package className="h-4 w-4 text-gray-500" />
                                Item Name
                            </Label>
                            <Input
                                type="text"
                                id="nama"
                                value={data.nama}
                                onChange={(e) => setData('nama', e.target.value)}
                                placeholder="e.g., Cappuccino, Espresso, Croissant..."
                                className={errors.nama ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.nama && (
                                <p className="text-sm text-red-600 flex items-center gap-1">
                                    <span className="font-medium">⚠</span> {errors.nama}
                                </p>
                            )}
                        </div>

                        {/* Category */}
                        <div className="space-y-2">
                            <Label htmlFor="kategori_id" className="flex items-center gap-2">
                                <Tag className="h-4 w-4 text-gray-500" />
                                Category
                            </Label>
                            <select
                                id="kategori_id"
                                value={data.kategori_id}
                                onChange={(e) => setData('kategori_id', e.target.value)}
                                className={`flex h-9 w-full rounded-md border ${
                                    errors.kategori_id ? 'border-red-500' : 'border-input'
                                } bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-all`}
                            >
                                <option value="">Select a category</option>
                                {kategoris.map(kategori => (
                                    <option key={kategori.id} value={kategori.id}>
                                        {kategori.nama}
                                    </option>
                                ))}
                            </select>
                            {errors.kategori_id && (
                                <p className="text-sm text-red-600 flex items-center gap-1">
                                    <span className="font-medium">⚠</span> {errors.kategori_id}
                                </p>
                            )}
                        </div>

                        {/* Outlet Assignment */}
                        <div className="space-y-3">
                            <Label className="flex items-center gap-2">
                                <Store className="h-4 w-4 text-gray-500" />
                                Assign to Outlets
                            </Label>
                            <p className="text-sm text-gray-500">
                                Select which outlets should carry this item.
                            </p>
                            
                            <div className="grid gap-2.5 sm:grid-cols-2">
                                {outlets.map((outlet, index) => (
                                    <div
                                        key={outlet.id}
                                        ref={el => outletRefs.current[index] = el}
                                        data-outlet-id={outlet.id}
                                        className="flex items-center space-x-2.5 rounded-lg border border-gray-200 p-3 transition-all hover:border-gray-300 hover:bg-gray-50/50"
                                    >
                                        <Checkbox
                                            id={`outlet-${outlet.id}`}
                                            checked={data.outlet_ids.includes(outlet.id)}
                                            onCheckedChange={() => handleOutletToggle(outlet.id)}
                                        />
                                        <Label
                                            htmlFor={`outlet-${outlet.id}`}
                                            className="flex-1 cursor-pointer text-sm font-medium leading-none"
                                        >
                                            <span className="flex items-center gap-2">
                                                <span className="text-base">{outlet.icon}</span>
                                                <span>{outlet.nama}</span>
                                            </span>
                                        </Label>
                                    </div>
                                ))}
                            </div>
                            
                            {errors.outlet_ids && (
                                <p className="text-sm text-red-600 flex items-center gap-1">
                                    <span className="font-medium">⚠</span> {errors.outlet_ids}
                                </p>
                            )}
                            
                            {data.outlet_ids.length === 0 && (
                                <p className="text-xs text-amber-600 flex items-center gap-1 bg-amber-50 px-2.5 py-2 rounded-md">
                                    <span className="font-medium">ℹ</span> 
                                    Select at least one outlet to continue.
                                </p>
                            )}
                        </div>

                        {mode === 'edit' && selectedOutlets.length > 0 && (
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
                                            ] ?? 'in_stock';

                                        return (
                                            <div
                                                key={outlet.id}
                                                className="rounded-lg border border-gray-200 p-3 shadow-sm"
                                            >
                                                <div className="mb-2 flex items-center justify-between text-sm font-medium text-gray-700">
                                                    <span className="flex items-center gap-2">
                                                        <span className="text-base">
                                                            {outlet.icon}
                                                        </span>
                                                        {outlet.nama}
                                                    </span>
                                                </div>
                                                <select
                                                    value={status}
                                                    onChange={(event) =>
                                                        handleStatusChange(
                                                            outlet.id,
                                                            event.target.value
                                                        )
                                                    }
                                                    className="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-200"
                                                >
                                                    {STATUS_OPTIONS.map(
                                                        ({ value, label }) => (
                                                            <option
                                                                key={value}
                                                                value={value}
                                                            >
                                                                {label}
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
                    </div>

                    <DialogFooter>
                        <Button 
                            type="button" 
                            variant="outline" 
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="submit" 
                            disabled={processing || data.outlet_ids.length === 0}
                            className="gap-2"
                        >
                            {processing ? (
                                <>
                                    <svg className="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Saving...
                                </>
                            ) : (
                                <>
                                    <Save className="h-4 w-4" />
                                    {mode === 'edit' ? 'Update Item' : 'Create Item'}
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
