export type Contact = {
    id: number;
    user_id: number;
    first_name: string;
    last_name: string | null;
    phone: string | null;
    email: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    zip: string | null;
    date_of_birth: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
};
