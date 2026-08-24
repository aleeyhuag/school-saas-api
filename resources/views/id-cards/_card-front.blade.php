{{-- Front of the CR80 student ID card. Keep this markup flat for Dompdf. --}}
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
    <div class="abs front-header-address">{{ $card['address_short'] ?: 'School Office' }}</div>
    <div class="abs front-header-phone">{{ $card['contact_short'] ?: 'Contact School' }}</div>

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

    <div class="abs front-fact-label" style="top: 68pt;">Adm No.</div>
    <div class="abs front-fact-value" style="top: 68pt;">{{ $card['student']->admission_number ?: '—' }}</div>
    <div class="abs front-fact-label" style="top: 76pt;">Class</div>
    <div class="abs front-fact-value" style="top: 76pt;">{{ $card['class_name'] }}</div>
    <div class="abs front-fact-label" style="top: 84pt;">D.O.B.</div>
    <div class="abs front-fact-value" style="top: 84pt;">{{ $card['student']->date_of_birth ? $card['student']->date_of_birth->format('d M Y') : '—' }}</div>

    @if($card['session_name'])
        <div class="abs front-session">{{ $card['session_name'] }}</div>
    @endif

    @if($card['signature_data_uri'])
        <img src="{{ $card['signature_data_uri'] }}" class="abs front-signature-img" alt="">
    @else
        <div class="abs front-signature-line"></div>
    @endif
    <div class="abs front-signature-label">PRINCIPAL</div>

    <div class="abs front-footer">
        Issued {{ $card['issued_on'] }} &nbsp;&bull;&nbsp; Property of {{ strtoupper($card['school']->name) }}
    </div>
</div>
