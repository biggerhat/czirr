<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';

function logout() {
    router.post('/logout');
}
</script>

<template>
    <AuthBase title="No Access" description="Your account is not linked to a family member.">
        <Head title="No Access" />
        <div class="text-center space-y-4">
            <p class="text-sm text-muted-foreground">
                Contact your family admin to be added.
            </p>
            <Button variant="outline" size="sm" @click="logout">
                Log out
            </Button>
        </div>
    </AuthBase>
</template>
