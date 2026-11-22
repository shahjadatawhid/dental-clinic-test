@include('errors.layout', [
    'code' => 404,
    'icon' => 'magnifying-glass',
    'heading' => __('Page not found'),
    'message' => __('The page or record you were looking for doesn’t exist. It may have been moved, or the link may be wrong.'),
])
