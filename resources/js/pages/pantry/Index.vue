<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import type { BreadcrumbItem } from '@/types';

interface Item {
    id: number;
    name: string;
}

const props = defineProps<{ items: Item[]; can: { edit: boolean } }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pantry' }];

const COMMON = [
    'Salt',
    'Black pepper',
    'Olive oil',
    'Vegetable oil',
    'Flour',
    'Sugar',
    'Baking powder',
    'Baking soda',
    'Vanilla extract',
    'Soy sauce',
    'Vinegar',
    'Garlic powder',
    'Onion powder',
    'Paprika',
    'Cumin',
    'Cinnamon',
    'Oregano',
    'Rice',
    'Pasta',
];

const name = ref('');
const busy = ref(false);

const have = computed(
    () => new Set(props.items.map((i) => i.name.toLowerCase())),
);
const suggestions = computed(() =>
    COMMON.filter((c) => !have.value.has(c.toLowerCase())),
);

const reload = () => router.reload({ only: ['items'] });

async function add(names: string[]) {
    if (busy.value || names.length === 0) return;
    busy.value = true;
    await api('POST', '/pantry', { names });
    busy.value = false;
    name.value = '';
    reload();
}

async function remove(item: Item) {
    if ((await api('DELETE', `/pantry/${item.id}`)).ok) reload();
}
</script>

<template>
    <Head title="Pantry" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">Pantry staples</h1>
                <p class="text-sm text-muted-foreground">
                    Things you always have at home. When you build a grocery
                    list from the meal plan, these are left off. Matching is
                    exact — add "brown sugar" separately if you keep it too.
                </p>
            </div>

            <form
                v-if="can.edit"
                class="flex gap-2"
                @submit.prevent="add([name])"
            >
                <Input
                    v-model="name"
                    maxlength="100"
                    placeholder="Add a staple…"
                />
                <Button type="submit" :disabled="busy || !name.trim()">
                    <Plus class="mr-1 h-4 w-4" /> Add
                </Button>
            </form>

            <div
                v-if="items.length === 0"
                class="py-6 text-center text-muted-foreground"
            >
                No staples yet.
            </div>
            <div v-else class="flex flex-wrap gap-2">
                <Badge
                    v-for="item in items"
                    :key="item.id"
                    variant="secondary"
                    class="gap-1 py-1 pr-1 pl-3 text-sm"
                >
                    {{ item.name }}
                    <button
                        v-if="can.edit"
                        type="button"
                        class="rounded-full p-0.5 hover:bg-background"
                        :aria-label="`Remove ${item.name}`"
                        @click="remove(item)"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </Badge>
            </div>

            <div v-if="can.edit && suggestions.length" class="space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-medium">Common staples</h2>
                    <Button
                        variant="ghost"
                        size="sm"
                        :disabled="busy"
                        @click="add(suggestions)"
                    >
                        Add all
                    </Button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="s in suggestions"
                        :key="s"
                        variant="outline"
                        size="sm"
                        class="h-7"
                        :disabled="busy"
                        @click="add([s])"
                    >
                        + {{ s }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
