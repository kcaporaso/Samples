$(document).ready(function (){
    fullname  = $('#name');

    if (fullname.length) {
        var fullNameVal = new LiveValidation('name', {
            validMessage: '',
            onlyOnBlur: true,
            insertAfterWhatNode: 'name_label',
            onValid: function() {
                if (fullname.val()) {
                   $('#name_validation').removeClass('invalid_field').addClass('valid_field');
                } else {
                   $('#name_validation').removeClass('invalid_field').removeClass('valid_field');
                }
            },
            onInvalid: function() {
                this.insertMessage( this.createMessageSpan() );
                this.addFieldClass();
                $('#name_validation').removeClass('valid_field').addClass('invalid_field');
            }
        });
        fullNameVal.add(Validate.Format, { pattern: /^\S+\s{1}\S+\s{0,}\S.*$/, failureMessage: 'Must be at least two words' });
    }

    if ($('#current_password').length) {

        var currentPasswordValid = new LiveValidation('current_password', {
            validMessage: ' ',
            insertAfterWhatNode: 'current_password_label',
            onlyOnSubmit: true
        });

        $('form').on('submit', function(event) {
            if ($('#password').val() && !$('#current_password').val()) {
                $('#password_validation').before('<span id="current_password_invalid_message_block" class="invalid_message">Enter Current Password.</span>');
                $('#current_password').focus(function() {
                    $('#current_password_invalid_message_block').remove();
                });
                event.preventDefault();
            } else {
                currentPasswordValid.destroy();
            }
        });

        var passwordValid = new LiveValidation('password', {
            validMessage: ' ',
            onlyOnBlur: true,
            insertAfterWhatNode: 'password_label',
            onlyOnSubmit: true
        });
        passwordValid.add(Validate.Length, { minimum: 6, tooShortMessage: 'Must be longer than 6 characters'});
        passwordValid.add(Validate.Format, { pattern: /(?=.{6,})((?=.*?[^\w\s])(?=.*?[0-9])(?=.*?[A-Z])|(?=.*?[^\w\s])(?=.*?[0-9])(?=.*?[a-z])|(?=.*?[0-9])(?=.*?[A-Z])(?=.*?[a-z])|(?=.*?[A-Z])(?=.*?[^\w\s])(?=.*?[a-z]))/, failureMessage: 'Need to include 3 of the following: uppercase, lowercase, number or special character.' });

        var passwordMatch = new LiveValidation('password2', {
            validMessage: ' ',
            onlyOnBlur: true,
            insertAfterWhatNode: 'password2_label',
            onlyOnSubmit: true
        });

        passwordMatch.add(Validate.Confirmation, { match: 'password', failureMessage: "Password must match" } );

    }

    if ($('.subscribe-success').length) {
        $('.subscribe-success').fadeOut('slow');
    }
});
