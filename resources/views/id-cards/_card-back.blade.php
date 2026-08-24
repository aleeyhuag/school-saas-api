{{-- Back of the CR80 student ID card. Keep this markup flat for Dompdf. --}}
<div class="id-card-inner">
    <div class="abs back-header">{{ strtoupper($card['school']->name) }}</div>

    <div class="abs back-pledge-title"><span>STUDENT CODE OF CONDUCT</span></div>
    <div class="abs back-pledge">
        As a student of {{ $card['school']->name }}, I pledge to uphold the
        values of discipline, respect, honesty and hard work.
    </div>
    <div class="abs back-pledge-list">
        <div>&bull; I will be punctual, diligent and respectful.</div>
        <div>&bull; I will wear my uniform neatly at all times.</div>
        <div>&bull; I will care for school property and the environment.</div>
    </div>

    <div class="abs back-emergency-title"><span>IN CASE OF EMERGENCY, CONTACT</span></div>
    <div class="abs back-emergency-label" style="top: 88pt;">Name</div>
    <div class="abs back-emergency-value" style="top: 88pt;">{{ $card['student']->guardian_name ?: '—' }}</div>
    <div class="abs back-emergency-label" style="top: 96pt;">Phone</div>
    <div class="abs back-emergency-value" style="top: 96pt;">{{ $card['student']->guardian_phone ?: '—' }}</div>

    <img src="{{ $card['qr_data_uri'] }}" class="abs back-qr" alt="">
    <div class="abs back-qr-label">SCAN TO VERIFY</div>

    <div class="abs back-footer"></div>
    <div class="abs back-sign-cell-l">
        <div class="back-signature-line"></div>
        <div class="back-signature-label">HOLDER'S SIGNATURE</div>
    </div>
    <div class="abs back-sign-divider"></div>
    <div class="abs back-sign-cell-r">
        @if($card['signature_data_uri'])
            <img src="{{ $card['signature_data_uri'] }}" class="back-signature-img" alt="">
        @else
            <div class="back-signature-line"></div>
        @endif
        <div class="back-signature-label">PRINCIPAL'S SIGNATURE</div>
    </div>
    <div class="abs back-notice">This ID card must be presented on demand. Not transferable.</div>
</div>
