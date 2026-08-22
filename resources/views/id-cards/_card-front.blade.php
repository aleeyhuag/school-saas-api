<div class="id-card-inner">
    <div class="abs front-header"></div>
    <div class="abs front-logo-wrap">
        @if($card['logo_data_uri'])
            <img src="{{ $card['logo_data_uri'] }}" class="front-logo" alt="">
        @else
            <div class="front-logo-placeholder">{{ strtoupper(substr($card['school']->name, 0, 1)) }}</div>
        @endif
    </div>
    <div class="abs front-school-name">{{ strtoupper($card['school']->name) }}</div>
    <div class="abs front-subtitle">ACADEMY</div>
    <div class="abs front-card-title">STUDENT IDENTITY CARD</div>

    <div class="abs front-photo-frame">
        @if($card['photo_data_uri'])
            <img src="{{ $card['photo_data_uri'] }}" class="front-photo" alt="">
        @else
            <div class="front-photo-placeholder">PHOTO</div>
        @endif
    </div>

    <div class="abs front-name">{{ $card['student']->full_name }}</div>
    <div class="abs front-name-rule"></div>
    <div class="abs front-role">STUDENT</div>

    <div class="abs front-fact-row" style="top: 68pt;">
        <span class="front-fact-label">Adm No.</span>
        <span class="front-fact-value">{{ $card['student']->admission_number ?: '—' }}</span>
    </div>
    <div class="abs front-fact-row" style="top: 76pt;">
        <span class="front-fact-label">Class</span>
        <span class="front-fact-value">{{ $card['class_name'] }}</span>
    </div>
    <div class="abs front-fact-row" style="top: 84pt;">
        <span class="front-fact-label">D.O.B.</span>
        <span class="front-fact-value">{{ $card['student']->date_of_birth ? $card['student']->date_of_birth->format('d M Y') : '—' }}</span>
    </div>

    @if($card['session_name'])
        <div class="abs front-session">{{ $card['session_name'] }}</div>
    @endif

    @if($card['signature_data_uri'])
        <img src="{{ $card['signature_data_uri'] }}" class="abs front-signature-img" alt="">
    @else
        <div class="abs front-signature-line"></div>
    @endif
    <div class="abs front-signature-label">PRINCIPAL</div>

    <div class="abs front-contact">
        <div class="front-contact-address">{{ $card['address_short'] ?: 'School Office' }}</div>
        <div class="front-contact-phone">{{ $card['contact_short'] ?: 'Contact School' }}</div>
    </div>

    <div class="abs front-footer">
        Issued {{ $card['issued_on'] }} &nbsp;&bull;&nbsp; Property of {{ strtoupper($card['school']->name) }}
    </div>
</div>
