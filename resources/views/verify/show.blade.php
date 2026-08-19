<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ID Verification — {{ $school->name }}</title>
    <style>
        body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; background: #f4f6f5; margin: 0; padding: 24px 16px; color: #1a1a1a; }
        .card { max-width: 380px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .status-bar { padding: 14px 20px; text-align: center; font-weight: 600; font-size: 15px; }
        .status-valid { background: #e6f4ea; color: #1e7d3c; }
        .status-inactive { background: #fdecea; color: #b3261e; }
        .content { padding: 24px 20px 28px; text-align: center; }
        .photo { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; border: 3px solid #f4f6f5; margin-bottom: 14px; }
        .photo-placeholder { width: 96px; height: 96px; border-radius: 50%; background: #e8e8e8; color: #999; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px; }
        .name { font-size: 19px; font-weight: 700; margin: 0 0 4px; }
        .meta { color: #666; font-size: 14px; margin: 2px 0; }
        .school-footer { border-top: 1px solid #eee; padding: 16px 20px; text-align: center; font-size: 13px; color: #888; }
        .school-logo { max-height: 28px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="status-bar {{ $isActive ? 'status-valid' : 'status-inactive' }}">
            {{ $isActive ? '✓ Valid Student ID' : 'Card No Longer Active' }}
        </div>
        <div class="content">
            @if($student->photo_url)
                <img src="{{ $student->photo_url }}" class="photo" alt="">
            @else
                <div class="photo-placeholder">No photo</div>
            @endif
            <div class="name">{{ $student->full_name }}</div>
            <div class="meta">{{ $className }}</div>
            <div class="meta">{{ $school->name }}</div>
        </div>
        <div class="school-footer">
            @if($school->logo_url)
                <img src="{{ $school->logo_url }}" class="school-logo" alt=""><br>
            @endif
            Verified via Skulag
        </div>
    </div>
</body>
</html>
