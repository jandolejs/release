
$( document ).ready(function() {
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })
});

const reloadPage = function (no_params) {

    /*if ($('#reloading_prevent') && $('#reloading_prevent').is(':checked')) {
        alert('Reloading is blocked');
        return;
    }*/

    if (no_params) window.location = window.location.href.split("?")[0];
    else location.reload();
}

function importAllToRelease() {
    $("#pulls_list input.to_test")
        .prop('checked', true);
    importReadyToRelease(); // add ready
}

function importReadyToRelease() {
    $("#pulls_list input.ready.to_test")
        .prop('checked', true);
}

function importToRelease(pull) {

    if (!pull){alert('Pull importing problem: pull is empty'); return;}
    if (!Number.isInteger(pull)) {
        pull = pull.match(/(\d+)\/?$/);
        pull = pull[1];
    }
    if (!pull) {
        pull = pull.match(/#(\d+)$/);
        pull = pull[1];
    }

    if (!pull){alert('Pull importing problem: pull does not end with number'); return;}

    let html = "<tr><td></td><td></td><td>#"+pull+"</td><td></td><td>Manually added</td><td></td><td>\n" +
        "    <label class=\"btn btn-secondary\">\n" +
        "        <input id=\"import_button_manual_" + pull + "\"" +
        "               type=\"checkbox\" autocomplete=\"off\" value=\"" + pull + "\"\n" +
        "        >Add&nbsp;to&nbsp;release<!-- Add to release --></label>\n" +
        "</td></tr>";

    $('#pulls_list').append(html);
    $('#import_button_manual_' + pull).prop('checked', true)
    $('#manual_add_pull').val('');
}

function reloadPulls(link) {
    reloading(true);
    window.location.href = link;
}

function prepareRelease (link, force = false) {
    let pulls = [];

    // Find selected pulls
    $("#pulls_list input")
        .filter(function() {
            if (this.checked) pulls.push(this.value);
        });

    // Check if any pull was selected
    if (pulls.length < 1) {
        alert("No pulls selected");
        return false;
    }

    // Prepare form
    let releaseFormId = 'prepare_release_form';
    let prepareForm = document.createElement("form");
    prepareForm.id = releaseFormId;
    prepareForm.action = link;
    prepareForm.method = 'GET';
    document.body.appendChild(prepareForm);

    // Add pulls as inputs to form
    let order = 1;
    pulls.forEach(
        function (pull) {
            $(prepareForm).append('<input type="text" id="tags" name="pulls['+order+++']" value="'+pull+'"/>');
        }
    );

    // Add force parameter
    if (force) $(prepareForm).append('<input type="checkbox" checked="checked" name="force">');

    // Send form to prepare release
    reloading(true);
    prepareForm.submit();
    console.log(prepareForm);
    document.body.removeChild(prepareForm);
}

function reloading(status) {
    if (status) $('#modal_reloading').modal('show')
    else $('#modal_reloading').modal('hide')
}

function sendRequest(link) {
    $.ajax({
        method: "GET",
        url: link,
        beforeSend: function () {
            reloading(true);
        },
        success: function () {
            reloadPage();
        },
        error: function (r) {
            alert(r.responseText);
            reloading(false);
        },
    })
}

function updateNote(link, el) {
    el = $(el);

    $.ajax({
        type: 'POST', url: link,
        data: {
            'note': el.val(),
        },
        beforeSend: function() {el.css('background-color', "lightyellow"); },
        success: function ()   {
            el.css('background-color', "lightgreen");
            setTimeout(function () {el.css('background-color', "white")}, 1200);
        },
        error: function ()     {el.css('background-color', "red"); },
        complete: function() {
        },
    });

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

function settingChanged(el) {
    if (el.value !== el.defaultValue) {
        el.setAttribute('style', 'background-color: white');
    } else {
        el.setAttribute('style', 'background-color: yellow');
    }
}
function saveSetting(button) {
    console.log($(button).closest('tr'));
}

function showLoginModal() {
    $('#modal_user').modal('show');
    $('#username').focus();
}

$(document).ready(function () {

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
