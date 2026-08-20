<table class="id-card">
    <tr>
        <td class="id-card-inner">
            <table class="card-front-header">
                <tr>
                    <td class="school-logo-wrap">
                        @if($card['logo_data_uri'])
                            <img src="{{ $card['logo_data_uri'] }}" class="school-logo" alt="">
                        @else
                            <div class="school-logo-placeholder">{{ strtoupper(substr($card['school']->name, 0, 1)) }}</div>
                        @endif
                    </td>
                    <td class="card-front-header-content">
                        <div class="card-front-school-name">{{ strtoupper($card['school']->name) }}</div>
                        <div class="card-front-school-subtitle">ACADEMY</div>
                        <div class="card-front-title">STUDENT IDENTITY CARD</div>
                    </td>
                </tr>
            </table>

            <table class="card-front-main">
                <tr>
                    <td class="card-front-photo-cell">
                        <table><tr><td class="card-front-photo-frame">
                            @if($card['photo_data_uri'])
                                <img src="{{ $card['photo_data_uri'] }}" class="card-front-photo" alt="">
                            @else
                                <div class="card-front-photo-placeholder">PHOTO</div>
                            @endif
                        </td></tr></table>
                    </td>
                    <td class="card-front-details">
                        <div class="card-front-name">{{ $card['student']->full_name }}</div>
                        <div class="card-front-name-rule"></div>
                        <div class="card-front-role">STUDENT</div>
                        <table class="card-front-facts">
                            <tr>
                                <td class="card-front-fact-label">Adm No.</td>
                                <td class="card-front-fact-value">{{ $card['student']->admission_number ?: '—' }}</td>
                            </tr>
                            <tr>
                                <td class="card-front-fact-label">Class</td>
                                <td class="card-front-fact-value">{{ $card['class_name'] }}</td>
                            </tr>
                            <tr>
                                <td class="card-front-fact-label">D.O.B.</td>
                                <td class="card-front-fact-value">
                                    {{ $card['student']->date_of_birth ? $card['student']->date_of_birth->format('d M Y') : '—' }}
                                </td>
                            </tr>
                        </table>
                        @if($card['session_name'])
                            <div class="card-front-session">{{ $card['session_name'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>

            <table class="card-front-lower">
                <tr>
                    <td class="card-front-brand-cell">
                        <div class="card-front-brand">Skulag<span class="ag"> by AG KOMPUTECH</span></div>
                    </td>
                    <td class="card-front-signature-cell">
                        @if($card['signature_data_uri'])
                            <img src="{{ $card['signature_data_uri'] }}" class="card-front-signature-img" alt="">
                        @else
                            <div class="card-front-signature-line"></div>
                        @endif
                        <div class="card-front-signature-label">PRINCIPAL</div>
                    </td>
                </tr>
            </table>

            <table class="card-front-contact">
                <tr>
                    <td class="card-front-contact-address">{{ $card['address_short'] ?: 'School Office' }}</td>
                    <td class="card-front-contact-phone">{{ $card['contact_short'] ?: 'Contact School' }}</td>
                </tr>
            </table>

            <div class="card-front-footer">
                Issued {{ $card['issued_on'] }} &nbsp; • &nbsp; This ID card is the property of {{ strtoupper($card['school']->name) }}
            </div>
        </td>
    </tr>
</table>
