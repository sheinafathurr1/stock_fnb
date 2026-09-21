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

export default function ResetReportsDialog({ open, onOpenChange, reportCount }) {
    const [deleting, setDeleting] = useState(false);

    const handleReset = () => {
        setDeleting(true);
        router.delete('/reports/reset', {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                setDeleting(false);
                toast.success('All reports have been deleted successfully!');
            },
            onError: () => {
                setDeleting(false);
                toast.error('Failed to delete reports. Please try again.');
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100">
                            <AlertTriangle className="h-4 w-4 text-red-600" />
                        </div>
                        Reset All Reports?
                    </DialogTitle>
                    <DialogDescription className="text-left pt-2">
                        Are you sure you want to delete <span className="font-semibold text-gray-900">all {reportCount} report{reportCount !== 1 ? 's' : ''}</span>?
                        <br />
                        <span className="text-red-600 font-medium">This action cannot be undone. All report history will be permanently removed.</span>
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
                            'Delete All Reports'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
