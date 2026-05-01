export const appRoutes = {
    home: () => route("home"),
    dashboard: () => route("dashboard"),
    plans: {
        index: () => route("plans.index"),
        show: (fincodePlanId: string) =>
            route("plans.show", {
                fincode_plan_id: fincodePlanId,
            }),
    },
    subscription: {
        index: () => route("subscription.index"),
        store: () => route("subscription.store"),
        destroy: (subscriptionId: number) =>
            route("subscription.destroy", {
                subscription: subscriptionId,
            }),
    },
    cards: {
        create: () => route("cards.create"),
        store: () => route("cards.store"),
        destroy: (cardId: number) =>
            route("cards.destroy", {
                card: cardId,
            }),
    },
    auth: {
        logout: () => route("logout"),
    },
} as const;
