export const DASHBOARD_TEMPLATE_STORAGE_KEY = "admin-dashboard-template";
export const DEFAULT_DASHBOARD_TEMPLATE = "owner-signature";

/*
 * Registry for genuinely different dashboard compositions. Keep color mode
 * (light/dark) outside this registry: a dashboard template must work in both.
 * New templates can add their own component and rootClass without changing
 * the dashboard controller or its response payload.
 */
export const dashboardTemplates = Object.freeze({
    "owner-signature": Object.freeze({
        key: "owner-signature",
        label: "Owner Signature",
        description: "Graphite, silver, dan amber dari dashboard owner.",
        rootClass: "dashboard-template-owner-signature",
        enabled: true,
    }),
});

export function resolveDashboardTemplate(key) {
    const template = dashboardTemplates[key];

    if (template?.enabled) {
        return template;
    }

    return dashboardTemplates[DEFAULT_DASHBOARD_TEMPLATE];
}

export function readDashboardTemplatePreference() {
    if (typeof window === "undefined") {
        return DEFAULT_DASHBOARD_TEMPLATE;
    }

    return resolveDashboardTemplate(
        window.localStorage.getItem(DASHBOARD_TEMPLATE_STORAGE_KEY),
    ).key;
}

