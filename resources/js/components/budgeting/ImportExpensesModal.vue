<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ 'update:open': [value: boolean]; imported: [] }>();

interface Result {
    imported: number;
    duplicates: number;
    skipped: number;
    errors: string[];
}

const file = ref<File | null>(null);
const busy = ref(false);
const error = ref<string | null>(null);
const result = ref<Result | null>(null);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        file.value = null;
        error.value = null;
        result.value = null;
    },
);

function pick(event: Event) {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null;
    error.value = null;
}

async function upload() {
    if (!file.value) return;
    busy.value = true;
    error.value = null;

    const body = new FormData();
    body.append('file', file.value);

    try {
        const res = await fetch('/expenses/import', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            body,
        });
        const data = await res.json().catch(() => null);

        if (res.ok) {
            result.value = data as Result;
            emit('imported');
        } else {
            error.value =
                data?.message ??
                data?.errors?.file?.[0] ??
                `Import failed (${res.status}).`;
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Import expenses from CSV</DialogTitle>
                <DialogDescription>
                    Export transactions from your bank or card as CSV. The file
                    needs a header row with date, description and amount
                    columns. Negative amounts are treated as spending, and
                    anything already imported is skipped.
                </DialogDescription>
            </DialogHeader>

            <input
                type="file"
                accept=".csv,text/csv,text/plain"
                class="block w-full text-sm file:mr-3 file:rounded-md file:border file:bg-muted file:px-3 file:py-1.5"
                @change="pick"
            />

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

            <div v-if="result" class="space-y-1 rounded-md border p-3 text-sm">
                <p class="font-medium">
                    Imported {{ result.imported }} expense{{
                        result.imported === 1 ? '' : 's'
                    }}.
                </p>
                <p v-if="result.duplicates" class="text-muted-foreground">
                    {{ result.duplicates }} already existed.
                </p>
                <p v-if="result.skipped" class="text-muted-foreground">
                    {{ result.skipped }} skipped (deposits or zero amounts).
                </p>
                <p v-for="e in result.errors" :key="e" class="text-amber-700 dark:text-amber-400">
                    {{ e }}
                </p>
                <p class="pt-1 text-xs text-muted-foreground">
                    Uncategorised rows went to "Other" — adjust them as needed.
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">
                    {{ result ? 'Done' : 'Cancel' }}
                </Button>
                <Button
                    v-if="!result"
                    :disabled="!file || busy"
                    @click="upload"
                >
                    {{ busy ? 'Importing…' : 'Import' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
