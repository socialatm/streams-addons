let channel_name = "";

function correctLinks() {
    $('.link_correction').each(function (i, obj) {
        path = window.location.pathname;
        path = path.substr(0, path.lastIndexOf("/help"));
        link = obj.href;
        link = link.replace("channel-nick", channel_name);
        obj.href = link;
    });
}

function correctWebdavUrl() {
    $('.webdavurl').each(function (i, obj) {
        let url = window.location.origin + "/dav/" + channel_name;
        obj.textContent = url;
    });
}

$(document).ready(function () {
    channel_name = window.location.pathname.split("/")[2];
    channel_name = channel_name.split("?")[0];
    correctLinks();
    correctWebdavUrl();
}
);

