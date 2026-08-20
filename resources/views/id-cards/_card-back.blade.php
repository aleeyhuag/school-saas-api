<table class="id-card">
    <tr>
        <td class="id-card-inner">
            <div class="card-back-header">{{ strtoupper($card['school']->name) }}</div>

            <div class="card-back-body">
                <div class="card-back-pledge-title">STUDENT CODE OF CONDUCT</div>
                <div class="card-back-pledge">
                    <p>As a student of {{ $card['school']->name }}, I pledge to uphold the values of discipline, respect, honesty and hard work.</p>
                    <ul>
                        <li>I will be punctual, diligent and respectful.</li>
                        <li>I will wear my uniform neatly at all times.</li>
                        <li>I will care for school property and the environment.</li>
                        <li>I will represent my school with pride and integrity.</li>
                    </ul>
                </div>

                <div class="card-back-emergency-title">IN CASE OF EMERGENCY, PLEASE CONTACT</div>
                <table class="card-back-emergency-wrap">
                    <tr>
                        <td class="card-back-emergency-info">
                            <table>
                                <tr>
                                    <td class="card-back-emergency-label">Name</td>
                                    <td>{{ $card['student']->guardian_name ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="card-back-emergency-label">Phone</td>
                                    <td>{{ $card['student']->guardian_phone ?: '—' }}</td>
                                </tr>
                            </table>
                        </td>
                        <td class="card-back-qr-cell">
                            <img src="{{ $card['qr_data_uri'] }}" class="card-back-qr" alt="">
                            <div class="card-back-qr-label">SCAN TO VERIFY</div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card-back-footer">
                <table class="card-back-signatures">
                    <tr>
                        <td class="card-back-signature-cell">
                            <div class="card-back-signature-line"></div>
                            <div class="card-back-signature-label">HOLDER'S SIGNATURE</div>
                        </td>
                        <td class="card-back-divider"></td>
                        <td class="card-back-signature-cell">
                            @if($card['signature_data_uri'])
                                <img src="{{ $card['signature_data_uri'] }}" class="card-back-signature-img" alt="">
                            @else
                                <div class="card-back-signature-line"></div>
                            @endif
                            <div class="card-back-signature-label">PRINCIPAL'S SIGNATURE</div>
                        </td>
                    </tr>
                </table>
                <div class="card-back-notice">This ID card must be presented on demand. Not transferable.</div>
            </div>
        </td>
    </tr>
</table>
