<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Trash2, Plus, Check, X, Link as LinkIcon, Unlink as UnlinkIcon, Lock, Shield } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { EVENT_COLORS } from '@/lib/calendar';
import type { BreadcrumbItem } from '@/types';
import type { EventColor, FamilyMember } from '@/types/calendar';

type LinkableUser = {
    id: number;
    name: string;
    email: string;
};

type RoleData = {
    id: number;
    name: string;
    is_default: boolean;
    permissions: string[];
    users_count: number;
};

type PermissionGroup = {
    value: string;
    action: string;
}[];

const props = defineProps<{
    familyMembers: FamilyMember[];
    linkableUsers: LinkableUser[];
    roles: RoleData[];
    availablePermissions: Record<string, PermissionGroup>;
    canManageRoles: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Family', href: '/family' },
];

const members = ref<FamilyMember[]>([...props.familyMembers]);
const availableUsers = ref<LinkableUser[]>([...props.linkableUsers]);
const roles = ref<RoleData[]>([...props.roles]);
const colorOptions: EventColor[] = ['rose', 'orange', 'amber', 'emerald', 'cyan', 'blue', 'violet', 'pink'];
const visibilityOptions = ['parent', 'child'] as const;
const roleNames = computed(() => roles.value.map(r => r.name));

// Add form state
const newName = ref('');
const newNickname = ref('');
const newVisibility = ref<'parent' | 'child'>('child');
const newSpatieRole = ref<string>('child');
const newColor = ref<EventColor>('blue');
const newLinkedUserId = ref<string>('none');
const isAdding = ref(false);

// Edit state
const editingId = ref<number | null>(null);
const editName = ref('');
const editNickname = ref('');
const editVisibility = ref<'parent' | 'child'>('child');
const editColor = ref<EventColor>('blue');
const editLinkedUserId = ref<string>('none');
const saveError = ref<string | null>(null);

// Users available for the edit dropdown (available + the currently linked one for this member)
const editLinkableUsers = computed(() => {
    const editing = members.value.find(m => m.id === editingId.value);
    if (!editing?.linked_user) return availableUsers.value;
    // Include the user already linked to this member so it shows in the dropdown
    const alreadyInList = availableUsers.value.some(u => u.id === editing.linked_user!.id);
    if (alreadyInList) return availableUsers.value;
    return [...availableUsers.value, editing.linked_user];
});

function getXsrfToken(): string {
    return decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

function updateAvailableUsers(oldLinkedUserId: number | null, newLinkedId: number | null) {
    // If we freed up a user, add them back
    if (oldLinkedUserId && oldLinkedUserId !== newLinkedId) {
        const member = members.value.find(m => m.linked_user_id === oldLinkedUserId);
        // Only add back if no other member is still linked to this user
        if (!member) {
            const user = props.linkableUsers.find(u => u.id === oldLinkedUserId)
                ?? props.familyMembers.find(m => m.linked_user_id === oldLinkedUserId)?.linked_user;
            if (user) {
                availableUsers.value.push({ id: user.id, name: user.name, email: user.email });
                availableUsers.value.sort((a, b) => a.name.localeCompare(b.name));
            }
        }
    }
    // If we claimed a user, remove them from available
    if (newLinkedId) {
        availableUsers.value = availableUsers.value.filter(u => u.id !== newLinkedId);
    }
}

async function addMember() {
    if (!newName.value.trim()) return;
    isAdding.value = true;

    const linkedId = newLinkedUserId.value && newLinkedUserId.value !== 'none' ? parseInt(newLinkedUserId.value) : null;

    try {
        const response = await fetch('/family', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                name: newName.value.trim(),
                nickname: newNickname.value.trim() || null,
                role: newVisibility.value,
                color: newColor.value,
                linked_user_id: linkedId,
            }),
        });

        if (response.ok) {
            const member: FamilyMember = await response.json();
            // If linked and a specific spatie role was chosen, assign it
            if (member.linked_user_id && newSpatieRole.value) {
                await updateMemberSpatieRole(member, newSpatieRole.value);
                // Re-find after role update
                const idx = members.value.findIndex(m => m.id === member.id);
                if (idx === -1) {
                    member.spatie_role = newSpatieRole.value;
                    members.value.push(member);
                }
            } else {
                members.value.push(member);
            }
            updateAvailableUsers(null, member.linked_user_id);
            newName.value = '';
            newNickname.value = '';
            newVisibility.value = 'child';
            newSpatieRole.value = 'child';
            newColor.value = 'blue';
            newLinkedUserId.value = 'none';
        }
    } finally {
        isAdding.value = false;
    }
}

function startEdit(member: FamilyMember) {
    editingId.value = member.id;
    editName.value = member.name;
    editNickname.value = member.nickname ?? '';
    editVisibility.value = member.role;
    editColor.value = member.color;
    editLinkedUserId.value = member.linked_user_id ? String(member.linked_user_id) : 'none';
    saveError.value = null;
}

function cancelEdit() {
    editingId.value = null;
}

async function saveEdit(member: FamilyMember) {
    if (!editName.value.trim()) return;
    saveError.value = null;

    try {
        const newLinkedId = editLinkedUserId.value && editLinkedUserId.value !== 'none' ? parseInt(editLinkedUserId.value) : null;

        const response = await fetch(`/family/${member.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                name: editName.value.trim(),
                nickname: editNickname.value.trim() || null,
                role: editVisibility.value,
                color: editColor.value,
                linked_user_id: newLinkedId,
            }),
        });

        if (response.ok) {
            const oldLinkedId = member.linked_user_id;
            const updated: FamilyMember = await response.json();
            const idx = members.value.findIndex(m => m.id === member.id);
            if (idx !== -1) {
                members.value[idx] = updated;
            }
            updateAvailableUsers(oldLinkedId, updated.linked_user_id);
            editingId.value = null;
        } else if (response.status === 422) {
            const data = await response.json();
            const messages = Object.values(data.errors ?? {}).flat() as string[];
            saveError.value = messages.join(', ') || 'Validation failed.';
        } else {
            saveError.value = `Save failed (${response.status}). Please try again.`;
        }
    } catch (e) {
        saveError.value = `Request failed: ${e instanceof Error ? e.message : 'Unknown error'}`;
    }
}

const showDeleteDialog = ref(false);
const deletingMember = ref<FamilyMember | null>(null);
const isDeletingMember = ref(false);
const deleteError = ref<string | null>(null);

function openDeleteMember(member: FamilyMember) {
    deletingMember.value = member;
    deleteError.value = null;
    showDeleteDialog.value = true;
}

async function confirmDeleteMember() {
    if (!deletingMember.value) return;
    isDeletingMember.value = true;
    deleteError.value = null;

    const response = await fetch(`/family/${deletingMember.value.id}`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
    });

    if (response.ok) {
        members.value = members.value.filter(m => m.id !== deletingMember.value!.id);
        updateAvailableUsers(deletingMember.value!.linked_user_id, null);
        isDeletingMember.value = false;
        showDeleteDialog.value = false;
    } else if (response.status === 422) {
        const data = await response.json();
        deleteError.value = data.message ?? 'Cannot delete this member.';
        isDeletingMember.value = false;
    } else {
        deleteError.value = `Delete failed (${response.status}). Please try again.`;
        isDeletingMember.value = false;
    }
}

// --- Spatie role assignment for linked members ---
async function updateMemberSpatieRole(member: FamilyMember, roleName: string) {
    const response = await fetch(`/family/${member.id}/role`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify({ role: roleName }),
    });

    if (response.ok) {
        const data = await response.json();
        const idx = members.value.findIndex(m => m.id === member.id);
        if (idx !== -1) {
            members.value[idx] = { ...members.value[idx], spatie_role: data.spatie_role };
        }
    }
}

// --- Roles & Permissions management ---
const showRoleModal = ref(false);
const editingRole = ref<RoleData | null>(null);
const roleFormName = ref('');
const roleFormPermissions = ref<string[]>([]);
const roleFormError = ref<string | null>(null);
const isSavingRole = ref(false);

function hasPermission(perm: string): boolean {
    return roleFormPermissions.value.includes(perm);
}

function hasAllPermissions(perms: PermissionGroup): boolean {
    return perms.every(p => roleFormPermissions.value.includes(p.value));
}

function openCreateRole() {
    editingRole.value = null;
    roleFormName.value = '';
    roleFormPermissions.value = [];
    roleFormError.value = null;
    showRoleModal.value = true;
}

function openEditRole(role: RoleData) {
    editingRole.value = role;
    roleFormName.value = role.name;
    roleFormPermissions.value = [...role.permissions];
    roleFormError.value = null;
    showRoleModal.value = true;
}

function togglePermission(perm: string) {
    const idx = roleFormPermissions.value.indexOf(perm);
    if (idx >= 0) {
        roleFormPermissions.value = roleFormPermissions.value.filter(p => p !== perm);
    } else {
        roleFormPermissions.value = [...roleFormPermissions.value, perm];
    }
}

function toggleModulePermissions(perms: PermissionGroup) {
    const allSelected = perms.every(p => roleFormPermissions.value.includes(p.value));
    if (allSelected) {
        const remove = new Set(perms.map(p => p.value));
        roleFormPermissions.value = roleFormPermissions.value.filter(p => !remove.has(p));
    } else {
        const current = new Set(roleFormPermissions.value);
        for (const p of perms) {
            current.add(p.value);
        }
        roleFormPermissions.value = [...current];
    }
}

async function saveRole() {
    roleFormError.value = null;
    const permissions = roleFormPermissions.value;

    if (permissions.length === 0) {
        roleFormError.value = 'At least one permission is required.';
        return;
    }

    isSavingRole.value = true;

    try {
        const isEdit = editingRole.value !== null;
        const url = isEdit ? `/family/roles/${editingRole.value!.id}` : '/family/roles';
        const method = isEdit ? 'PUT' : 'POST';

        const body: Record<string, unknown> = { permissions };
        if (!isEdit || !editingRole.value!.is_default) {
            body.name = roleFormName.value.trim();
        }

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify(body),
        });

        if (response.ok) {
            const data: RoleData = await response.json();
            if (isEdit) {
                const idx = roles.value.findIndex(r => r.id === editingRole.value!.id);
                if (idx !== -1) roles.value[idx] = data;
            } else {
                roles.value.push(data);
            }
            showRoleModal.value = false;
        } else if (response.status === 422) {
            const data = await response.json();
            const messages = Object.values(data.errors ?? {}).flat() as string[];
            roleFormError.value = messages.join(', ') || data.message || 'Validation failed.';
        } else {
            roleFormError.value = `Save failed (${response.status}).`;
        }
    } catch (e) {
        roleFormError.value = `Request failed: ${e instanceof Error ? e.message : 'Unknown error'}`;
    } finally {
        isSavingRole.value = false;
    }
}

const showDeleteRoleDialog = ref(false);
const deletingRole = ref<RoleData | null>(null);
const isDeletingRole = ref(false);
const deleteRoleError = ref<string | null>(null);

function openDeleteRole(role: RoleData) {
    deletingRole.value = role;
    deleteRoleError.value = null;
    showDeleteRoleDialog.value = true;
}

async function confirmDeleteRole() {
    if (!deletingRole.value) return;
    isDeletingRole.value = true;
    deleteRoleError.value = null;

    const response = await fetch(`/family/roles/${deletingRole.value.id}`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
    });

    if (response.ok) {
        roles.value = roles.value.filter(r => r.id !== deletingRole.value!.id);
        isDeletingRole.value = false;
        showDeleteRoleDialog.value = false;
    } else {
        const data = await response.json().catch(() => ({}));
        deleteRoleError.value = data.message ?? `Delete failed (${response.status}).`;
        isDeletingRole.value = false;
    }
}

function formatModuleName(module: string): string {
    return module.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Family" />

        <div class="px-4 py-6">
            <div class="mx-auto max-w-xl space-y-6">
                <Heading
                    variant="small"
                    title="Family Members"
                    description="Manage family members that can be assigned to calendar events. Link them to user accounts so they can log in."
                />

                <!-- Add form -->
                <div class="rounded-md border p-4 space-y-3">
                    <div class="text-sm font-medium">Add family member</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="text-sm text-muted-foreground">Name</label>
                            <Input
                                v-model="newName"
                                placeholder="Name"
                                @keydown.enter="addMember"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-sm text-muted-foreground">Nickname</label>
                            <Input
                                v-model="newNickname"
                                placeholder="Nickname (optional)"
                                @keydown.enter="addMember"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-sm text-muted-foreground">Visibility</label>
                            <Select v-model="newVisibility">
                                <SelectTrigger>
                                    <SelectValue placeholder="Visibility" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="v in visibilityOptions" :key="v" :value="v">
                                        {{ v.charAt(0).toUpperCase() + v.slice(1) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="availableUsers.length > 0" class="space-y-1.5">
                            <label class="text-sm text-muted-foreground">Link account</label>
                            <Select v-model="newLinkedUserId">
                                <SelectTrigger>
                                    <SelectValue placeholder="No account" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">No account</SelectItem>
                                    <SelectItem v-for="u in availableUsers" :key="u.id" :value="String(u.id)">
                                        {{ u.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="newLinkedUserId !== 'none'" class="space-y-1.5">
                            <label class="text-sm text-muted-foreground">Role</label>
                            <Select v-model="newSpatieRole">
                                <SelectTrigger>
                                    <SelectValue placeholder="Role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="r in roleNames" :key="r" :value="r">
                                        {{ r.charAt(0).toUpperCase() + r.slice(1) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex gap-1.5">
                            <button
                                v-for="c in colorOptions"
                                :key="c"
                                type="button"
                                :class="[
                                    'w-6 h-6 rounded-full transition-all',
                                    EVENT_COLORS[c].dot,
                                    newColor === c ? 'ring-2 ring-offset-2 ring-offset-background ring-foreground scale-110' : 'opacity-50 hover:opacity-100',
                                ]"
                                @click="newColor = c"
                            />
                        </div>
                        <Button @click="addMember" :disabled="isAdding || !newName.trim()" size="sm">
                            <Plus class="h-4 w-4 mr-1" />
                            Add
                        </Button>
                    </div>
                </div>

                <!-- Members list -->
                <div v-if="members.length > 0" class="divide-y divide-border rounded-md border">
                    <div
                        v-for="member in members"
                        :key="member.id"
                        class="px-4 py-3"
                    >
                        <!-- Edit mode -->
                        <template v-if="editingId === member.id">
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <div :class="['w-3 h-3 rounded-full shrink-0', EVENT_COLORS[editColor]?.dot ?? 'bg-blue-500']" />
                                    <Input
                                        v-model="editName"
                                        class="flex-1 h-8"
                                        placeholder="Name"
                                        @keydown.enter="saveEdit(member)"
                                        @keydown.escape="cancelEdit"
                                    />
                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="saveEdit(member)">
                                        <Check class="h-4 w-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="cancelEdit">
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                                <div class="pl-6 space-y-1.5">
                                    <label class="text-sm text-muted-foreground">Nickname</label>
                                    <Input
                                        v-model="editNickname"
                                        class="h-8"
                                        placeholder="Nickname (optional)"
                                        @keydown.enter="saveEdit(member)"
                                        @keydown.escape="cancelEdit"
                                    />
                                </div>
                                <div class="pl-6 space-y-1.5">
                                    <label class="text-sm text-muted-foreground">Visibility</label>
                                    <Select v-model="editVisibility">
                                        <SelectTrigger class="h-8">
                                            <SelectValue placeholder="Visibility" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="v in visibilityOptions" :key="v" :value="v">
                                                {{ v.charAt(0).toUpperCase() + v.slice(1) }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex items-center gap-3 pl-6">
                                    <div class="flex gap-1">
                                        <button
                                            v-for="c in colorOptions"
                                            :key="c"
                                            type="button"
                                            :class="[
                                                'w-5 h-5 rounded-full transition-all',
                                                EVENT_COLORS[c].dot,
                                                editColor === c ? 'ring-2 ring-offset-1 ring-offset-background ring-foreground scale-110' : 'opacity-40 hover:opacity-100',
                                            ]"
                                            @click="editColor = c"
                                        />
                                    </div>
                                </div>
                                <div class="pl-6">
                                    <label class="text-sm text-muted-foreground">Linked account</label>
                                    <Select v-model="editLinkedUserId" class="mt-1">
                                        <SelectTrigger class="h-8">
                                            <SelectValue placeholder="No account" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No account</SelectItem>
                                            <SelectItem v-for="u in editLinkableUsers" :key="u.id" :value="String(u.id)">
                                                {{ u.name }} ({{ u.email }})
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <p v-if="saveError" class="pl-6 text-sm text-destructive">{{ saveError }}</p>
                            </div>
                        </template>

                        <!-- Display mode -->
                        <template v-else>
                            <div class="flex items-center gap-3">
                                <div :class="['w-3 h-3 rounded-full shrink-0', EVENT_COLORS[member.color]?.dot ?? 'bg-blue-500']" />
                                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-0 flex-1 min-w-0">
                                    <span class="text-sm font-medium">{{ member.nickname ?? member.name }}</span>
                                    <span v-if="member.nickname" class="text-sm text-muted-foreground sm:ml-1.5">({{ member.name }})</span>
                                    <div class="flex flex-wrap items-center gap-1 sm:ml-1.5">
                                        <span v-if="member.spatie_role" class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-medium rounded bg-primary/10 text-primary">
                                            <Shield class="h-3 w-3" />
                                            {{ member.spatie_role }}
                                        </span>
                                        <span :class="[
                                            'inline-flex items-center px-1.5 py-0.5 text-xs rounded',
                                            'bg-muted/50 text-muted-foreground/70',
                                        ]">{{ member.role }}</span>
                                        <span v-if="member.linked_user" class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                            <LinkIcon class="h-3 w-3" />
                                            {{ member.linked_user.email }}
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1 text-xs text-muted-foreground/50">
                                            <UnlinkIcon class="h-3 w-3" />
                                            No account
                                        </span>
                                    </div>
                                </div>
                                <!-- Spatie role dropdown for linked members -->
                                <Select
                                    v-if="canManageRoles && member.linked_user"
                                    :model-value="member.spatie_role ?? ''"
                                    @update:model-value="updateMemberSpatieRole(member, String($event))"
                                >
                                    <SelectTrigger class="h-7 w-full sm:w-[110px] text-xs">
                                        <SelectValue placeholder="Role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="role in roles" :key="role.id" :value="role.name">
                                            {{ role.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button variant="ghost" size="icon" class="h-8 w-8 shrink-0" @click="startEdit(member)">
                                    <Pencil class="h-3.5 w-3.5" />
                                </Button>
                                <Button variant="ghost" size="icon" class="h-8 w-8 shrink-0 text-destructive" @click="openDeleteMember(member)">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </template>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                    No family members yet. Add one above to get started.
                </div>

                <!-- Delete member dialog -->
                <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Remove family member?</DialogTitle>
                            <DialogDescription>
                                This will permanently remove "{{ deletingMember?.name }}" from your family. This cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <p v-if="deleteError" class="text-sm text-destructive">{{ deleteError }}</p>
                        <DialogFooter class="gap-2">
                            <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                            <Button variant="destructive" :disabled="isDeletingMember" @click="confirmDeleteMember">
                                {{ isDeletingMember ? 'Removing...' : 'Remove' }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <!-- === Roles & Permissions Section === -->
                <template v-if="canManageRoles">
                    <Heading
                        variant="small"
                        title="Roles & Permissions"
                        description="Manage roles and their permissions. Default roles cannot be renamed or deleted."
                    />

                    <div class="space-y-3">
                        <!-- Role cards -->
                        <div
                            v-for="role in roles"
                            :key="role.id"
                            class="rounded-md border p-4 space-y-2"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Lock v-if="role.is_default" class="h-3.5 w-3.5 text-muted-foreground" />
                                    <span class="font-medium text-sm">{{ role.name }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        ({{ role.permissions.length }} permissions, {{ role.users_count }} {{ role.users_count === 1 ? 'user' : 'users' }})
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="openEditRole(role)">
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        v-if="!role.is_default"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive"
                                        @click="openDeleteRole(role)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="perm in role.permissions.slice(0, 8)"
                                    :key="perm"
                                    class="px-1.5 py-0.5 text-xs rounded bg-muted text-muted-foreground"
                                >
                                    {{ perm }}
                                </span>
                                <span v-if="role.permissions.length > 8" class="px-1.5 py-0.5 text-xs rounded bg-muted text-muted-foreground">
                                    +{{ role.permissions.length - 8 }} more
                                </span>
                            </div>
                        </div>

                        <Button size="sm" @click="openCreateRole">
                            <Plus class="h-4 w-4 mr-1" />
                            Create Role
                        </Button>
                    </div>

                    <!-- Role create/edit modal -->
                    <Dialog :open="showRoleModal" @update:open="showRoleModal = $event">
                        <DialogContent class="sm:max-w-lg max-h-[80vh] overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle>{{ editingRole ? 'Edit Role' : 'Create Role' }}</DialogTitle>
                                <DialogDescription>
                                    {{ editingRole ? 'Update permissions for this role.' : 'Create a new role with specific permissions.' }}
                                </DialogDescription>
                            </DialogHeader>

                            <div class="space-y-4 py-2">
                                <div v-if="!editingRole || !editingRole.is_default" class="space-y-1.5">
                                    <label class="text-sm font-medium">Name</label>
                                    <Input v-model="roleFormName" placeholder="e.g. Teen" />
                                </div>

                                <div class="space-y-3">
                                    <label class="text-sm font-medium">Permissions</label>
                                    <div
                                        v-for="(perms, module) in availablePermissions"
                                        :key="module"
                                        class="space-y-1.5"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Checkbox
                                                :model-value="hasAllPermissions(perms)"
                                                @update:model-value="toggleModulePermissions(perms)"
                                            />
                                            <span class="text-sm font-medium">{{ formatModuleName(module as string) }}</span>
                                        </div>
                                        <div class="ml-3 sm:ml-6 flex flex-wrap gap-x-4 gap-y-1">
                                            <label
                                                v-for="perm in perms"
                                                :key="perm.value"
                                                class="flex items-center gap-1.5 text-sm text-muted-foreground cursor-pointer"
                                            >
                                                <Checkbox
                                                    :model-value="hasPermission(perm.value)"
                                                    @update:model-value="togglePermission(perm.value)"
                                                />
                                                {{ perm.action }}
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="roleFormError" class="text-sm text-destructive">{{ roleFormError }}</p>
                            </div>

                            <DialogFooter class="gap-2">
                                <Button variant="outline" @click="showRoleModal = false">Cancel</Button>
                                <Button :disabled="isSavingRole" @click="saveRole">
                                    {{ isSavingRole ? 'Saving...' : (editingRole ? 'Update' : 'Create') }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <!-- Delete role dialog -->
                    <Dialog :open="showDeleteRoleDialog" @update:open="showDeleteRoleDialog = $event">
                        <DialogContent class="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Delete role?</DialogTitle>
                                <DialogDescription>
                                    This will permanently delete the "{{ deletingRole?.name }}" role. This cannot be undone.
                                </DialogDescription>
                            </DialogHeader>
                            <p v-if="deleteRoleError" class="text-sm text-destructive">{{ deleteRoleError }}</p>
                            <DialogFooter class="gap-2">
                                <Button variant="outline" @click="showDeleteRoleDialog = false">Cancel</Button>
                                <Button variant="destructive" :disabled="isDeletingRole" @click="confirmDeleteRole">
                                    {{ isDeletingRole ? 'Deleting...' : 'Delete' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
