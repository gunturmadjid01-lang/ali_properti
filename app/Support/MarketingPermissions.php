<?php

namespace App\Support;

final class MarketingPermissions
{
    public static function operational(): array
    {
        return [
            'dashboard.view', 'perumahan.view', 'detail-rumah.view', 'unit-stock.view', 'pricelist.view', 'payment-simulation.view',
            'customer.view', 'customer.create', 'customer.update', 'customer.lock',
            'customer-follow-up.view', 'customer-follow-up.create', 'customer-follow-up.update', 'customer-follow-up.lock', 'customer.follow-up',
            'marketing-reminder.view', 'marketing-reminder.create', 'marketing-reminder.update',
            'marketing-visit.view', 'marketing-visit.create', 'marketing-visit.update', 'marketing-visit.lock',
            'marketing-survey.view', 'marketing-survey.create', 'marketing-survey.update', 'marketing-survey.lock',
            'marketing-action-plan.view', 'marketing-action-plan.create', 'marketing-action-plan.update', 'marketing-action-plan.lock',
            'customer-document-checklist.view', 'customer-document-checklist.create', 'customer-document-checklist.update', 'customer-document-checklist.lock',
            'marketing.pipeline.view', 'marketing.lead-report.view', 'marketing.activity.view',
            'marketing-calendar.view',
            'marketing-lead.view', 'marketing-lead.create', 'marketing-lead.update', 'marketing-lead.qualify', 'marketing-lead.convert',
            'marketing-activity-contact.create', 'marketing-activity-contact.convert',
            'marketing.lead-assignment.view', 'marketing.lead-assignment.respond',
            'marketing-evaluation.view',
            'marketing-report.view',
            'booking.view', 'booking.create', 'booking.update', 'booking.manage',
            'housing-reservation.view', 'housing-reservation.create', 'housing-reservation.update', 'housing-reservation.lock', 'housing-reservation.print',
            'cash-sale.view', 'cash-sale.create', 'cash-sale.update', 'cash-sale.lock',
            'kpr.view', 'kpr.update',
            'dokumen-customer.view', 'dokumen-customer.create', 'dokumen-customer.update',
            'progress.view',
        ];
    }
}
