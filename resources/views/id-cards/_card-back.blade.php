{{-- Flat direct-child layout keeps Dompdf's coordinate system deterministic. --}}
<div class="id-card-inner">
    <div class="abs back-header">{{ strtoupper($card['school_name_display']) }}</div>

    <div class="abs back-pledge-title">STUDENT CODE OF CONDUCT</div>
    <div class="abs back-pledge">
        As a student of {{ $card['school']->name }}, I pledge to uphold the
        values of discipline, respect, honesty and hard work.
    </div>
    <div class="abs back-pledge-list">
        <div>&bull; I will be punctual, diligent and respectful.</div>
        <div>&bull; I will wear my uniform neatly at all times.</div>
        <div>&bull; I will care for school property and the environment.</div>
    </div>

    <div class="abs back-emergency-title">IN CASE OF EMERGENCY, CONTACT</div>
    <div class="abs back-emergency-label" style="top: 94pt;">Name</div>
    <div class="abs back-emergency-value" style="top: 94pt;">{{ $card['student']->guardian_name ?: '—' }}</div>
    <div class="abs back-emergency-label" style="top: 102pt;">Phone</div>
    <div class="abs back-emergency-value" style="top: 102pt;">{{ $card['student']->guardian_phone ?: '—' }}</div>

    <img src="{{ $card['qr_data_uri'] }}" class="abs back-qr" alt="">
    <div class="abs back-qr-label">SCAN TO VERIFY</div>

    <div class="abs back-footer"></div>
    <div class="abs back-holder-line"></div>
    <div class="abs back-holder-label">HOLDER'S SIGNATURE</div>
    <div class="abs back-sign-divider"></div>
    @if($card['signature_data_uri'])
        <img src="{{ $card['signature_data_uri'] }}" class="abs back-principal-signature-img" alt="">
    @endif
    <div class="abs back-principal-line"></div>
    <div class="abs back-principal-label">PRINCIPAL'S SIGNATURE</div>
    <div class="abs back-notice">This ID card must be presented on demand. Not transferable.</div>
</div>
