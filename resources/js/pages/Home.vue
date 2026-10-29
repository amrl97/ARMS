<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    FilePlus2,
    LayoutGrid,
    Settings2,
    ShieldCheck,
    SquareText,
    UsersRound,
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Home',
                href: '/home',
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const shortcuts = computed(() => {
    const roles = page.props.auth.roles;
    const isAdmin = roles.includes('Super Admin') || roles.includes('Admin');
    const canReview =
        isAdmin || roles.includes('Approver') || page.props.auth.canApprove;

    return [
        ...(!isAdmin
            ? [
                  {
                      title: 'Submit a request',
                      description: 'Request access to the systems you need.',
                      href: '/requests/create',
                      icon: FilePlus2,
                      tone: 'bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300',
                  },
                  {
                      title: 'My requests',
                      description: 'Follow the progress of submitted requests.',
                      href: '/requests',
                      icon: SquareText,
                      tone: 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
                  },
              ]
            : []),
        ...(canReview
            ? [
                  {
                      title: 'Approval inbox',
                      description: 'Review access waiting for your decision.',
                      href: '/approvals',
                      icon: ShieldCheck,
                      tone: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
                  },
              ]
            : []),
        ...(isAdmin
            ? [
                  {
                      title: 'Manage users',
                      description: 'Maintain people and system access.',
                      href: '/users',
                      icon: UsersRound,
                      tone: 'bg-violet-50 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
                  },
                  {
                      title: 'System configuration',
                      description: 'Manage access modules and approvers.',
                      href: '/admin/configs',
                      icon: Settings2,
                      tone: 'bg-orange-50 text-orange-700 dark:bg-orange-950/60 dark:text-orange-300',
                  },
              ]
            : []),
    ];
});
</script>

<template>
    <Head title="Home" />

    <main class="mx-auto w-full flex-1 space-y-8 p-4 sm:p-6 lg:p-8">
        <section
            class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-teal-950 px-6 py-8 text-white shadow-xl shadow-slate-950/10 sm:px-10 sm:py-11"
        >
            <div
                class="pointer-events-none absolute -top-32 -right-20 -z-10 size-96 rounded-full bg-teal-400/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute right-1/4 -bottom-36 -z-10 size-72 rounded-full bg-sky-400/10 blur-3xl"
            />
            <div class="max-w-2xl">
                <div
                    class="mb-5 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-medium text-teal-100"
                >
                    <LayoutGrid class="size-3.5" />
                    Access Request Management System
                </div>
                <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                    Welcome back, {{ user.fullname }}
                </h1>
                <p
                    class="mt-3 max-w-xl text-sm leading-6 text-slate-300 sm:text-base"
                >
                    Your workspace for requesting, reviewing, and managing
                    system access.
                </p>
            </div>
        </section>

        <section aria-labelledby="shortcuts-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2
                        id="shortcuts-heading"
                        class="text-lg font-semibold tracking-tight"
                    >
                        Quick access
                    </h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Your most-used access management tools.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="shortcut in shortcuts"
                    :key="shortcut.title"
                    :href="shortcut.href"
                    class="group bg-card hover:border-primary/30 focus-visible:ring-ring flex min-h-40 flex-col rounded-2xl border p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-900/5 focus-visible:ring-2 focus-visible:outline-none"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div
                            class="flex size-11 items-center justify-center rounded-xl"
                            :class="shortcut.tone"
                        >
                            <component :is="shortcut.icon" class="size-5" />
                        </div>
                        <ArrowRight
                            class="text-muted-foreground group-hover:text-primary mt-1 size-4 transition group-hover:translate-x-1"
                        />
                    </div>
                    <h3 class="mt-5 font-semibold tracking-tight">
                        {{ shortcut.title }}
                    </h3>
                    <p class="text-muted-foreground mt-1 text-sm leading-5">
                        {{ shortcut.description }}
                    </p>
                </Link>
            </div>
        </section>
    </main>
</template>
