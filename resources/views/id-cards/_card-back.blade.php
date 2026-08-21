<div class="id-card-inner">
    <div class="abs back-header">{{ strtoupper($card['school']->name) }}</div>

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
    <div class="abs back-emergency-row" style="top: 94pt;">
        <span class="back-emergency-label">Name</span>
        <span class="back-emergency-value">{{ $card['student']->guardian_name ?: '—' }}</span>
    </div>
    <div class="abs back-emergency-row" style="top: 102pt;">
        <span class="back-emergency-label">Phone</span>
        <span class="back-emergency-value">{{ $card['student']->guardian_phone ?: '—' }}</span>
    </div>

    <img src="{{ $card['qr_data_uri'] }}" class="abs back-qr" alt="">
    <div class="abs back-qr-label">SCAN TO VERIFY</div>

    <div class="abs back-footer">
        <div class="abs back-sign-cell-l">
            @if($card['signature_data_uri'])
                <img src="{{ $card['signature_data_uri'] }}" class="back-signature-img" alt="">
            @else
                <div class="back-signature-line"></div>
            @endif
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
</div>
