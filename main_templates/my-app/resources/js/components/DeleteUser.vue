<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <Card class="border-red-500/25 bg-red-500/5 shadow-none">
        <CardHeader class="gap-3 p-6">
            <Badge
                variant="destructive"
                class="w-fit rounded-full px-3 py-1 text-[11px] tracking-[0.2em] uppercase"
            >
                Danger zone
            </Badge>
            <div class="flex items-start gap-3">
                <div
                    class="flex size-10 items-center justify-center rounded-2xl bg-red-500/15 text-red-600 dark:text-red-300"
                >
                    <AlertTriangle class="size-5" />
                </div>
                <div class="space-y-1">
                    <CardTitle class="text-xl">Delete account</CardTitle>
                    <CardDescription class="max-w-2xl text-sm leading-6">
                        Permanently remove your account and all associated
                        resources. This action cannot be undone.
                    </CardDescription>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 p-6 pt-0">
            <div
                class="rounded-2xl border border-red-500/20 bg-background/70 p-4 text-sm leading-6 text-muted-foreground"
            >
                If you no longer need access, confirm the deletion using your
                password. All personal data and connected resources tied to this
                account will be removed.
            </div>

            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        class="rounded-xl"
                        data-test="delete-user-button"
                    >
                        Delete account
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.$el?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>
                                Are you sure you want to delete your account?
                            </DialogTitle>
                            <DialogDescription>
                                Once your account is deleted, all of its
                                resources and data will also be permanently
                                deleted. Please enter your password to confirm
                                this action.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">
                                Password
                            </Label>
                            <Input
                                id="password"
                                ref="passwordInput"
                                type="password"
                                name="password"
                                class="h-11 rounded-xl"
                                placeholder="Password"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    Cancel
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                Delete account
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </CardContent>
    </Card>
</template>
