@include('errors.layout', [
    'code' => 419,
    'icon' => 'clock',
    'heading' => __('This page has expired'),
    'message' => __('You may have been signed out after a period of inactivity. Please go back, reload the page and try again — anything not saved will need to be entered again.'),
])
