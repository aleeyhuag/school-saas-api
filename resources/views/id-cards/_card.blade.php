{{-- Shared by id-cards.single and id-cards.print-sheet — one card's
     markup lives here only, so the two output shapes can never
     visually drift apart from each other. Table-based layout
     throughout; dompdf renders a subset of CSS (no flexbox/grid). --}}
<table class="id-card">
    <tr>
        <td class="id-card-inner">
            <table class="id-card-header">
                <tr>
                    <td class="id-card-logo-cell">
                        @if($card['school']->logo_url)
                            <img src="{{ $card['school']->logo_url }}" class="id-card-logo">
                        @endif
                    </td>
                    <td class="id-card-school-name">
                        {{ $card['school']->name }}
                        <div class="id-card-subtitle">STUDENT IDENTITY CARD</div>
                    </td>
                </tr>
            </table>

            <table class="id-card-body">
                <tr>
                    <td class="id-card-photo-cell">
                        @if($card['photo_data_uri'])
                            <img src="{{ $card['photo_data_uri'] }}" class="id-card-photo">
                        @else
                            <div class="id-card-photo-placeholder">PHOTO</div>
                        @endif
                    </td>
                    <td class="id-card-details">
                        <div class="id-card-student-name">{{ $card['student']->full_name }}</div>
                        <table class="id-card-facts">
                            <tr><td class="id-card-fact-label">Class</td><td>{{ $card['class_name'] }}</td></tr>
                            <tr><td class="id-card-fact-label">Adm. No.</td><td>{{ $card['student']->admission_number }}</td></tr>
                            @if($card['student']->date_of_birth)
                                <tr><td class="id-card-fact-label">D.O.B.</td><td>{{ $card['student']->date_of_birth->format('d M Y') }}</td></tr>
                            @endif
                        </table>
                    </td>
                    <td class="id-card-qr-cell">
                        <img src="{{ $card['qr_data_uri'] }}" class="id-card-qr">
                        <div class="id-card-qr-label">Scan to verify</div>
                    </td>
                </tr>
            </table>

            <table class="id-card-footer">
                <tr>
                    <td>Issued {{ $card['issued_on'] }}</td>
                    <td class="id-card-footer-right">If found, please return to {{ $card['school']->name }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
