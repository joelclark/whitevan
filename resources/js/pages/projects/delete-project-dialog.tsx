import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Project } from '@/types';

type Props = {
    project: Project;
    trigger: ReactNode;
};

export default function DeleteProjectDialog({ project, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const estimateCount = project.estimates?.length ?? 0;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {project.name}?</DialogTitle>
                <DialogDescription>
                    {estimateCount > 0
                        ? `This project has ${estimateCount} estimate${
                              estimateCount === 1 ? '' : 's'
                          }. Deleting the project will archive all of them.`
                        : 'This project will be moved to the archive.'}
                </DialogDescription>

                <Form
                    {...ProjectController.destroy.form(project.id)}
                    options={{ preserveScroll: false }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary" type="button">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                            >
                                Delete project
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
