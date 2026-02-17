<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow p-4" style="width:350px">
        <h4 class="text-center mb-3">Login</h4>

        <form method="POST" action="/login">
            @csrf

            <div class="mb-3">
                <input name="email" class="form-control" placeholder="Email">
            </div>

            <div class="mb-3">
                <input name="password" type="password" class="form-control" placeholder="Password">
            </div>

            <button class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>