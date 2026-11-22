@include('errors.layout', [
    'code' => 403,
    'icon' => 'lock-closed',
    'heading' => __('You don’t have access to this page'),
    'message' => __('Your role doesn’t include this part of the system. If you need it for your work, ask the clinic administrator to update your access.'),
])
