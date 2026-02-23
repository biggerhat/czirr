import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(() => (page.props as any).permissions ?? []);
    const userRole = computed<string | null>(() => (page.props as any).userRole ?? null);

    function can(permission: string): boolean {
        return permissions.value.includes(permission);
    }

    function canAny(perms: string[]): boolean {
        return perms.some(p => permissions.value.includes(p));
    }

    function hasRole(role: string): boolean {
        return userRole.value === role;
    }

    return { permissions, userRole, can, canAny, hasRole };
}
