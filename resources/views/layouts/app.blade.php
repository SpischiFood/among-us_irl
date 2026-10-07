<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Among Us IRL')</title>
    <link rel="stylesheet" href="{{ ('css/app.css') }}">

    <script src="{{ ('js/app.js') }}" defer></script>
    
</head>
<body>

    @yield('content')
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    @stack('scripts')

</body>
</html>