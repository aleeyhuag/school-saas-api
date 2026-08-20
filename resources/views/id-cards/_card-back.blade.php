{{-- Back of the card — code of conduct, emergency contact, and the
     QR verification code (deliberately here, not the front, per this
     stage's redesign). The pledge text is fixed/generic for now, not
     per-school customizable — flagged in the delivery notes as a
     possible later addition, not something silently assumed out of
     scope. --}}
<table class="id-card">
    <tr>
        <td class="id-card-inner">
            <div class="card-back-header">{{ $card['school']->name }}</div>

            <div class="card-back-body">
                <div class="card-back-pledge-title">STUDENT CODE OF CONDUCT</div>
                <div class="card-back-pledge">
                    As a student of {{ $card['school']->name }}, I pledge to uphold
                    the values of discipline, respect, honesty and hard work.
                    <ul>
                        <li>I will be punctual, diligent and respectful.</li>
                        <li>I will wear my uniform neatly at all times.</li>
                        <li>I will care for school property and the environment.</li>
                    </ul>
                </div>

                <div class="card-back-emergency-title">IN CASE OF EMERGENCY, CONTACT</div>
                <table class="card-back-emergency">
                    <tr>
                        <td class="card-back-emergency-label">Name</td>
                        <td>{{ $card['student']->guardian_name ?: '—' }}</td>
                        <td rowspan="2" style="text-align: right; vertical-align: middle;">
                            <img src="{{ $card['qr_data_uri'] }}" class="card-back-qr">
                            <div class="card-back-qr-label">Scan to verify</div>
                        </td>
                    </tr>
                    <tr>
                        <td class="card-back-emergency-label">Phone</td>
                        <td>{{ $card['student']->guardian_phone ?: '—' }}</td>
                    </tr>
                </table>
            </div>

            <div class="card-back-footer">
                <div class="card-back-sign-row">
                    <span class="card-back-sign-line">&nbsp;</span> &nbsp;&nbsp;&nbsp;
                    <span class="card-back-sign-line">&nbsp;</span><br>
                    Holder's Signature &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Principal's Signature
                </div>
                <div class="card-back-notice">This ID card must be presented on demand. Not transferable.</div>
            </div>
        </td>
    </tr>
</table>
