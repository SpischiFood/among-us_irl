<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Game</title>
</head>
<body>
     @if (session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif
    <h1>Create Game</h1>
    <form action="{{ route('games.store') }}" method="POST">
        @csrf
        <label for="name">Game Name:</label>
        <input type="text" id="name" name="name" required>
        <button type="submit">Create Game</button>
    </form>
</body>
</html>