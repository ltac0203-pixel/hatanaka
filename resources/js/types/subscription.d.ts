export type PlanStatus = "active" | "inactive" | "archived";

export type SubscriptionStatus =
    | "active"
    | "canceled"
    | "expired"
    | "unpaid"
    | "incomplete";

export type SubscriptionResultStatus =
    | "success"
    | "failed"
    | "pending"
    | "canceled";

export interface Plan {
    id: string;
    fincode_plan_id: string;
    name: string;
    description: string | null;
    amount: number;
    interval: string;
    interval_count: number;
    status: PlanStatus;
    features: string[] | null;
    price_display: string;
    interval_label: string;
}

export interface FincodeCard {
    id: number;
    brand: string;
    last4: string;
    exp_month: number;
    exp_year: number;
    holder_name: string | null;
    is_default: boolean;
    is_expired: boolean;
    display_name: string;
    expiry_display: string;
}

export interface Subscription {
    id: number;
    fincode_subscription_id: string;
    status: SubscriptionStatus;
    start_date: string;
    stop_date: string | null;
    next_charge_date: string | null;
    canceled_at: string | null;
    plan: Plan | null;
    card: FincodeCard | null;
}

export interface SubscriptionResult {
    id: number;
    status: SubscriptionResultStatus;
    amount: number;
    tax: number | null;
    charged_at_date: string;
    error_code: string | null;
    error_message: string | null;
}
