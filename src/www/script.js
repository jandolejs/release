
$( document ).ready(function() {
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })
});

const reloadPage = function (no_params) {
    if (no_params) window.location = window.location.href.split("?")[0];
    else location.reload();
}

function reloading(status) {
    if (status) $('#modal_reloading').modal('show')
    else $('#modal_reloading').modal('hide')
}

function tryLogin() {
    let m = $('#login_message');

    $.ajax({
        type: 'POST', url: "/login/username",
        data: {
            'username': $('#username').val(),
            'password': $('#password').val(),
        },
        beforeSend: function() {
            m.slideUp();
            m.removeClass('alert-danger').removeClass('alert-success');
        },
        success: function (r) {
            m.addClass('alert-success');
            m.text('Successfully logged in');
            setTimeout(reloadPage, 1000);
        },
        error: function (r) {
            console.log(r);
            m.addClass('alert-danger');
            m.text(r.responseText);
        },
        complete: function() {
            m.slideDown();
        },
    });
}

function showLoginModal() {
    $('#modal_user').modal('show');
    setTimeout(function(){$('#username').focus(); }, 500);
}

$(function() {

    $('#username').keypress(function (e) {
        if (e.which === 13) {
            $('#password').focus();
        }
    });

    $('#password').keypress(function (e) {
        if (e.which === 13) {
            tryLogin();
        }
    });
});
