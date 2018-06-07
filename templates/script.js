var screenshots = document.querySelectorAll('.screenshot');
var modal = document.querySelector('.modal');
var modalLink = modal.querySelector('.modal-link');
var modalImage = modal.querySelector('.modal-image');
var modalCaption = modal.querySelector('.modal-caption');
var modalClose = modal.querySelector('.modal-close');
var modalLeft = modal.querySelector('.modal-left');
var modalRight = modal.querySelector('.modal-right');

function getPrevNextImgs() {
    for (var i = 0; i < screenshots.length; i++) {
        var screenshot = screenshots[i];
        var src = screenshot.querySelector('img').src;
        if (src.replace('/thumb-', '/') != modalImage.src) {
            continue
        }
        var prevImg = (i != 0)
            ? screenshots[i - 1].querySelector('img')
            : null;
        var nextImg = (i != screenshots.length - 1)
            ? screenshots[i + 1].querySelector('img')
            : null;
        return {
            src: src,
            prevImg: prevImg,
            nextImg: nextImg
        };
    }
}

function showImage(img) {
    modal.style.display = 'block';
    var src = img.src.replace('/thumb-', '/');
    modalLink.href = src + '?path=' + img.getAttribute('data-path');
    modalImage.src = src;
    modalCaption.innerHTML = img.alt;
    var imgs = getPrevNextImgs();
    modalLeft.style.display = imgs.prevImg ? 'block' : 'none';
    modalRight.style.display = imgs.nextImg ? 'block' : 'none';
}

for (var i = 0; i < screenshots.length; i++) {
    var screenshot = screenshots[i];
    var img = screenshot.querySelector('img');
    screenshot.addEventListener('click', showImage.bind(null, img));
}

modalClose.addEventListener('click', function() {
    modal.style.display = 'none';
});

modalLeft.addEventListener('click', function() {
    var imgs = getPrevNextImgs();
    if (imgs.prevImg) {
        showImage(imgs.prevImg);
    }
});

modalRight.addEventListener('click', function () {
    var imgs = getPrevNextImgs();
    if (imgs.nextImg) {
        showImage(imgs.nextImg);
    }
});

document.addEventListener('keydown', function(evt) {
    switch (evt.keyCode) {
        case 27:
            // esc
            modalClose.click();
            break;
        case 37:
            // left arrow
            modalLeft.click();
            break;
        case 39:
            // right arrow
            modalRight.click();
            break;
    }
});
