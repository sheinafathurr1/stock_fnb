import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';

export default function ResetReportsDialog({
    open,
    onOpenChange,
    reportCount,
    outletId = '',
    outletName = '',
    date = '',
}) {
    const [deleting, setDeleting] = useState(false);

    const handleReset = () => {
        setDeleting(true);
        // Send the filters currently on screen: the server deletes exactly the
        // reports this dialog is describing, not every report ever submitted.
        router.delete(route('reports.reset', { date, outlet: outletId || undefined }), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                setDeleting(false);
                toast.success('Reports deleted successfully!');
            },
            onError: () => {
                setDeleting(false);
                toast.error('Failed to delete reports. Please try again.');
            },
        });
    };

    const scope = outletName ? `${outletName} on ${date}` : `all outlets on ${date}`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100">
                            <AlertTriangle className="h-4 w-4 text-red-600" />
                        </div>
                        Delete these reports?
                    </DialogTitle>
                    <DialogDescription className="text-left pt-2">
                        This deletes the <span className="font-semibold text-gray-900">{reportCount} report{reportCount !== 1 ? 's' : ''}</span> currently shown for <span className="font-semibold text-gray-900">{scope}</span>. Reports for other days and outlets are left alone.
                        <br />
                        <span className="text-red-600 font-medium">This action cannot be undone.</span>
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button 
                        type="button" 
                        variant="outline" 
                        onClick={() => onOpenChange(false)}
                        disabled={deleting}
                    >
                        Cancel
                    </Button>
                    <Button 
                        type="button" 
                        variant="destructive"
                        onClick={handleReset}
                        disabled={deleting}
                        className="gap-2"
                    >
                        {deleting ? (
                            <>
                                <svg className="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Deleting...
                            </>
                        ) : (
                            'Delete These Reports'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
