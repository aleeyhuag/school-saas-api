{{-- Front of the card. `_card.blade.php` (front+back combined, single
     side) is no longer used by single.blade.php/print-sheet.blade.php
     — safe to delete from resources/views/id-cards/ if you'd rather
     not leave it sitting around unused. --}}
<table class="id-card">
    <tr>
        <td class="id-card-inner">
            <table class="card-front-header">
                <tr>
                    <td style="width: 30pt;">
                        @if($card['school']->logo_url)
                            <img src="{{ $card['school']->logo_url }}" class="card-front-logo">
                        @endif
                    </td>
                    <td>
                        {{ $card['school']->name }}
                        <div class="card-front-tagline">STUDENT IDENTITY CARD</div>
                    </td>
                </tr>
            </table>

            <table class="card-front-body">
                <tr>
                    <td class="card-front-photo-cell">
                        @if($card['photo_data_uri'])
                            <img src="{{ $card['photo_data_uri'] }}" class="card-front-photo">
                        @else
                            <div class="card-front-photo-placeholder">PHOTO</div>
                        @endif
                    </td>
                    <td class="card-front-details">
                        <div class="card-front-name">{{ $card['student']->full_name }}</div>
                        <div class="card-front-role">Student</div>
                        <table class="card-front-facts">
                            <tr><td class="card-front-fact-label">Adm. No.</td><td>{{ $card['student']->admission_number }}</td></tr>
                            <tr><td class="card-front-fact-label">Class</td><td>{{ $card['class_name'] }}</td></tr>
                            @if($card['student']->date_of_birth)
                                <tr><td class="card-front-fact-label">D.O.B.</td><td>{{ $card['student']->date_of_birth->format('d M Y') }}</td></tr>
                            @endif
                        </table>
                        @if($card['session_name'])
                            <div class="card-front-session">{{ $card['session_name'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>

            <table class="card-front-signoff">
                <tr>
                    <td class="card-front-brand">Skulag</td>
                    <td class="card-front-signature-cell">
                        @if($card['signature_data_uri'])
                            <img src="{{ $card['signature_data_uri'] }}" class="card-front-signature-img">
                        @else
                            <div class="card-front-signature-line">&nbsp;</div>
                        @endif
                        <div class="card-front-signature-label">PRINCIPAL</div>
                    </td>
                </tr>
            </table>

            <div class="card-front-footer">
                THIS CARD IS THE PROPERTY OF {{ strtoupper($card['school']->name) }} — IF FOUND, PLEASE RETURN TO THE SCHOOL OFFICE
            </div>
        </td>
    </tr>
</table>
