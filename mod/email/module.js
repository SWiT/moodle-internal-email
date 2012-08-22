M.mod_email = M.mod_email || {};
M.mod_email.nav = M.mod_email.nav || {}; 

M.mod_email.init_sendmail_form = function(Y) {
    M.mod_email.nav.Y = Y;
    Y.on('click', M.mod_email.confirm_cancel, '#id_cancel' );
};

M.mod_email.confirm_cancel = function(e) {    
    var Y = M.mod_email.nav.Y;
    var msg = "Your message has not been sent.\nOK to continue composing the email.\nCancel to discard this message.";
    if(confirm(msg)){
        e.halt();
    }
}