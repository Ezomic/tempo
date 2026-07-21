<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import GarminController from '@/actions/App/Http/Controllers/Settings/GarminController';
import Heading from '@/components/Heading.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as editGarmin } from '@/routes/garmin';

interface Connection {
    status: string;
    display_name: string | null;
    sync_status: string;
    sync_error: string | null;
    last_synced_at_diff: string | null;
}

interface Settings {
    max_hr: number | null;
    resting_hr: number | null;
    lthr: number | null;
    sex: string;
}

interface Stats {
    activities: number;
    wellness_days: number;
}

const props = defineProps<{
    connection: Connection | null;
    settings: Settings;
    stats: Stats;
    login_token: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Garmin', href: editGarmin() }],
    },
});

const page = usePage();
const status = computed(() => page.props.status as string | undefined);
const isConnected = computed(() => props.connection?.status === 'connected');
const awaitingMfa = computed(() => !!props.login_token);
</script>

<template>
    <Head title="Garmin" />

    <h1 class="sr-only">Garmin settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Garmin"
            description="Connect your Garmin account and sync activities and wellness."
        />

        <div
            v-if="status"
            class="rounded-md bg-muted px-4 py-3 text-sm text-muted-foreground"
        >
            {{ status }}
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Connection</CardTitle>
                <CardDescription>
                    <template v-if="isConnected">
                        Connected<template v-if="connection?.display_name">
                            as {{ connection.display_name }}</template
                        >.
                    </template>
                    <template v-else>Not connected.</template>
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <template v-if="isConnected">
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <Badge
                            :variant="
                                connection?.sync_status === 'error'
                                    ? 'destructive'
                                    : 'secondary'
                            "
                        >
                            {{ connection?.sync_status }}
                        </Badge>
                        <span
                            v-if="connection?.last_synced_at_diff"
                            class="text-muted-foreground"
                        >
                            Last synced {{ connection.last_synced_at_diff }}
                        </span>
                    </div>
                    <p
                        v-if="connection?.sync_error"
                        class="text-sm text-destructive"
                    >
                        {{ connection.sync_error }}
                    </p>
                    <div class="flex gap-3">
                        <Form
                            v-bind="GarminController.sync.form()"
                            v-slot="{ processing }"
                        >
                            <Button type="submit" :disabled="processing">
                                Sync now
                            </Button>
                        </Form>
                        <Form
                            v-bind="GarminController.disconnect.form()"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing"
                            >
                                Disconnect
                            </Button>
                        </Form>
                    </div>
                </template>

                <template v-else-if="awaitingMfa">
                    <Form
                        v-bind="GarminController.mfa.form()"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <input
                            type="hidden"
                            name="login_token"
                            :value="login_token ?? ''"
                        />
                        <div class="grid max-w-xs gap-2">
                            <Label for="code">Authenticator code</Label>
                            <Input
                                id="code"
                                name="code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                required
                            />
                            <InputError :message="errors.code" />
                        </div>
                        <Button type="submit" :disabled="processing">
                            Finish connecting
                        </Button>
                    </Form>
                </template>

                <template v-else>
                    <Form
                        v-bind="GarminController.connect.form()"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid max-w-sm gap-2">
                            <Label for="email">Garmin email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autocomplete="username"
                                required
                            />
                            <InputError :message="errors.email" />
                        </div>
                        <div class="grid max-w-sm gap-2">
                            <Label for="password">Garmin password</Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                            />
                            <InputError :message="errors.password" />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Your password is sent once to your own sync service
                            to sign in and is never stored.
                        </p>
                        <Button type="submit" :disabled="processing">
                            Connect
                        </Button>
                    </Form>
                </template>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Heart-rate settings</CardTitle>
                <CardDescription>
                    Used to compute training load (TRIMP) comparably across
                    running and cycling. Accurate max HR matters most.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="GarminController.updateSettings.form()"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="max_hr">Max HR</Label>
                            <Input
                                id="max_hr"
                                name="max_hr"
                                type="number"
                                :default-value="settings.max_hr ?? ''"
                            />
                            <InputError :message="errors.max_hr" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="resting_hr">Resting HR</Label>
                            <Input
                                id="resting_hr"
                                name="resting_hr"
                                type="number"
                                :default-value="settings.resting_hr ?? ''"
                            />
                            <InputError :message="errors.resting_hr" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="lthr">Threshold HR</Label>
                            <Input
                                id="lthr"
                                name="lthr"
                                type="number"
                                :default-value="settings.lthr ?? ''"
                            />
                            <InputError :message="errors.lthr" />
                        </div>
                    </div>
                    <div class="grid max-w-xs gap-2">
                        <Label for="sex">Sex (TRIMP weighting)</Label>
                        <select
                            id="sex"
                            name="sex"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option
                                value="male"
                                :selected="settings.sex === 'male'"
                            >
                                Male
                            </option>
                            <option
                                value="female"
                                :selected="settings.sex === 'female'"
                            >
                                Female
                            </option>
                        </select>
                        <InputError :message="errors.sex" />
                    </div>
                    <Button type="submit" :disabled="processing">Save</Button>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Synced data</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex gap-8 text-sm">
                    <div>
                        <div class="text-2xl font-semibold">
                            {{ stats.activities }}
                        </div>
                        <div class="text-muted-foreground">Activities</div>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold">
                            {{ stats.wellness_days }}
                        </div>
                        <div class="text-muted-foreground">Wellness days</div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
