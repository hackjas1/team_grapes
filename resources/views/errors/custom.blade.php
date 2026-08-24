<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'BSIS Attendance Portal' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #063B5C; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; }
        .card-container { max-width: 480px; width: 100%; background: #FFFFFF; border-radius: 20px; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); text-align: center; }
    </style>
</head>
<body>
    <div class="card-container">
        <div class="d-flex justify-content-center align-items-center gap-3 mb-3">
            <img src="/images/tpc-logo.png" alt="TPC Logo" style="height: 54px;">
            <img src="/images/bsis-logo.png" alt="BSIS Logo" style="height: 54px;">
        </div>
        <h5 class="fw-bold text-primary mb-1">Talibon Polytechnic College</h5>
        <p class="text-muted small mb-4">BSIS Student Attendance System</p>

        <div class="mb-3">
            <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
        </div>

        <h5 class="fw-bold text-dark mb-2">{{ $title ?? 'Notice' }}</h5>
        <p class="text-muted small mb-4" style="line-height: 1.6;">{{ $message ?? 'Please check your institutional email for your personalized activation link.' }}</p>

        <a href="/" class="btn btn-primary w-100 py-2 fw-bold" style="background-color: #063B5C; border: none; border-radius: 10px;">
            <i class="bi bi-arrow-left me-1"></i> Return to Homepage
        </a>
    </div>
</body>
</html>
