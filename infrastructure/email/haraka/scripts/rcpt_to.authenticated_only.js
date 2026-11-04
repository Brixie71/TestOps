exports.hook_rcpt = function (next, connection) {
    if (connection.relaying === true) {
        return next(OK);
    }

    return next(DENY, 'SMTP authentication required');
};
