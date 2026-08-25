<?php

return [
    'forecast_stage_weights' => [
        'new' => (float) env('CRM_FORECAST_WEIGHT_NEW', 0.05),
        'contacted' => (float) env('CRM_FORECAST_WEIGHT_CONTACTED', 0.10),
        'qualified' => (float) env('CRM_FORECAST_WEIGHT_QUALIFIED', 0.30),
        'survey' => (float) env('CRM_FORECAST_WEIGHT_SURVEY', 0.50),
        'negotiation' => (float) env('CRM_FORECAST_WEIGHT_NEGOTIATION', 0.70),
        'reservation' => (float) env('CRM_FORECAST_WEIGHT_RESERVATION', 0.85),
    ],
];
