

function correctLinks() {
    $('.link_correction').each(function (i, obj) {
        path = window.location.pathname;
        path = path.substr(0,path.lastIndexOf("/help"));
        channel = path.substr(path.lastIndexOf("/") + 1);
        link = obj.href;
        link = link.replace("channel-nick", channel);
        obj.href = link;
    });
}

$(document).ready(function () {
    correctLinks();
}
);

