<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login/code';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Sign in with an email code or a passkey',
    },
});

defineProps<{ status?: string }>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <PasskeyVerify />

        <div
            class="text-center text-xs tracking-wide text-muted-foreground uppercase"
        >
            Or continue with email
        </div>

        <Form v-bind="store.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="mt-6">
                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Send login code
                </Button>
            </div>
        </Form>
    </div>
</template>
