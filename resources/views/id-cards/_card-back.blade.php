<div class="id-card-page">
    <div class="abs back-header"><span class="center-text">{{ strtoupper($card['school']->name) }}</span></div>
    <div class="abs back-pledge-title"><span class="pill-text">STUDENT CODE OF CONDUCT</span></div>
    <div class="abs back-pledge">As a student of {{ $card['school']->name }}, I pledge to uphold the values of discipline, respect, honesty and hard work.</div>
    <div class="abs back-pledge-list">
        <div>&bull; I will be punctual, diligent and respectful.</div>
        <div>&bull; I will wear my uniform neatly at all times.</div>
        <div>&bull; I will care for school property and the environment.</div>
    </div>
    <div class="abs back-emergency-title"><span class="pill-text">IN CASE OF EMERGENCY, CONTACT</span></div>
    <div class="abs back-emergency-label name">Name</div>
    <div class="abs back-emergency-value name">{{ $card['student']->guardian_name ?: '—' }}</div>
    <div class="abs back-emergency-label phone">Phone</div>
    <div class="abs back-emergency-value phone">{{ $card['student']->guardian_phone ?: '—' }}</div>
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
    <div class="abs back-notice"><span class="center-text">This ID card must be presented on demand. Not transferable.</span></div>
</div>
