<?php

return [
  'inactive_default_months' => (int) env('CRM_INACTIVE_MONTHS', 6),

  'follow_up_reminder_enabled' => (bool) env('CRM_FOLLOW_UP_REMINDERS', true),

  'messaging' => [
    'driver' => env('CRM_MESSAGING_DRIVER', 'log'),
    'default_country_code' => env('CRM_DEFAULT_COUNTRY_CODE', '55'),
    'twilio' => [
      'account_sid' => env('TWILIO_ACCOUNT_SID'),
      'auth_token' => env('TWILIO_AUTH_TOKEN'),
      'sms_from' => env('TWILIO_SMS_FROM'),
      'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],
  ],

  'templates' => [
    'inactive_campaign' => 'Olá {nome}, sentimos sua falta! Temos equipamentos disponíveis para sua obra. Posso ajudar com um orçamento?',
    'follow_up_reminder' => 'Olá {nome}, passando para retomar nosso contato comercial. Quando podemos conversar?',
    'return_reminder' => 'Olá {nome}, lembrete sobre o retorno do equipamento. Precisa de ajuda?',
  ],
];
