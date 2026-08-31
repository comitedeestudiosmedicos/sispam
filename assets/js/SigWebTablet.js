// Topaz SigWebTablet JavaScript Library for Topaz Systems
// Self-contained, conflict-free SDK for SISPAM & Modern Browsers

(function (window) {
    'use strict';

    var getBlobURL = (window.URL && URL.createObjectURL.bind(URL)) || (window.webkitURL && webkitURL.createObjectURL.bind(webkitURL)) || window.createObjectURL;
    var revokeBlobURL = (window.URL && URL.revokeObjectURL.bind(URL)) || (window.webkitURL && webkitURL.revokeObjectURL.bind(webkitURL)) || window.revokeObjectURL;

    var displayCtx = null;
    var tabletTimer = null;

    function getBaseUri() {
        var prot = window.location.protocol;
        if (prot === 'file:') prot = 'http:';
        if (prot === 'https:') {
            return 'https://tablet.sigwebtablet.com:47290/SigWeb/';
        } else {
            return 'http://tablet.sigwebtablet.com:47289/SigWeb/';
        }
    }

    function generateUUID() {
        var d = new Date().getTime();
        if (typeof performance !== 'undefined' && typeof performance.now === 'function') {
            d += performance.now();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (d + Math.random() * 16) % 16 | 0;
            d = Math.floor(d / 16);
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function setProperty(prop) {
        var xhr = new XMLHttpRequest();
        try {
            xhr.open('POST', getBaseUri() + prop, false);
            xhr.send(null);
            if (xhr.readyState === 4 && xhr.status === 200) {
                return xhr.responseText;
            }
        } catch(e) {}
        return '';
    }

    function getProperty(prop) {
        var xhr = new XMLHttpRequest();
        try {
            xhr.open('GET', getBaseUri() + prop + '?noCache=' + generateUUID(), false);
            xhr.send(null);
            if (xhr.readyState === 4 && xhr.status === 200) {
                return xhr.responseText;
            }
        } catch(e) {}
        return '';
    }

    function isSigWebInstalled() {
        var xhr = new XMLHttpRequest();
        try {
            xhr.open('GET', getBaseUri() + 'TabletState?noCache=' + generateUUID(), false);
            xhr.send(null);
            return (xhr.status !== 404 && xhr.status !== 0);
        } catch (e) {
            return false;
        }
    }

    function setDisplayTarget(obj) {
        displayCtx = obj;
    }

    function resetTablet() {
        setProperty('Reset');
    }

    function clearTablet() {
        setProperty('Reset');
    }

    function setTabletState(state, targetCtx, refreshRate) {
        if (state === 1) {
            if (targetCtx) displayCtx = targetCtx;
            setProperty('TabletState/1');
            if (refreshRate && refreshRate > 0) {
                if (tabletTimer) clearInterval(tabletTimer);
                tabletTimer = setInterval(sigWebRefresh, refreshRate);
                return tabletTimer;
            }
        } else {
            if (targetCtx && typeof targetCtx === 'number') {
                clearInterval(targetCtx);
            } else if (tabletTimer) {
                clearInterval(tabletTimer);
                tabletTimer = null;
            }
            setProperty('TabletState/0');
        }
        return 0;
    }

    function getTabletState() {
        return getProperty('TabletState');
    }

    function setImageXSize(val) {
        setProperty('ImageXSize/' + val);
    }

    function setImageYSize(val) {
        setProperty('ImageYSize/' + val);
    }

    function setImagePenWidth(val) {
        setProperty('ImagePenWidth/' + val);
    }

    function getImageXSize() {
        var s = getProperty('ImageXSize');
        return s ? parseInt(s, 10) : 500;
    }

    function getImageYSize() {
        var s = getProperty('ImageYSize');
        return s ? parseInt(s, 10) : 200;
    }

    function sigWebRefresh() {
        if (!displayCtx) return;
        var xhr2 = new XMLHttpRequest();
        xhr2.open('GET', getBaseUri() + 'SigImage/0?noCache=' + generateUUID(), true);
        xhr2.responseType = 'blob';
        xhr2.onload = function () {
            if (xhr2.status === 200 && xhr2.response && xhr2.response.size > 1400) {
                var img = new Image();
                var bUrl = getBlobURL(xhr2.response);
                img.onload = function () {
                    displayCtx.clearRect(0, 0, displayCtx.canvas.width, displayCtx.canvas.height);
                    displayCtx.drawImage(img, 0, 0, displayCtx.canvas.width, displayCtx.canvas.height);
                    revokeBlobURL(bUrl);
                };
                img.src = bUrl;
            }
        };
        xhr2.send(null);
    }

    function getSigImageB64(callback) {
        var cvs = document.createElement('canvas');
        cvs.width = getImageXSize() || 500;
        cvs.height = getImageYSize() || 200;

        var xhr2 = new XMLHttpRequest();
        xhr2.open('GET', getBaseUri() + 'SigImage/1?noCache=' + generateUUID(), true);
        xhr2.responseType = 'blob';
        xhr2.onload = function () {
            if (xhr2.status === 200 && xhr2.response && xhr2.response.size > 1400) {
                var cntx = cvs.getContext('2d');
                var img = new Image();
                var bUrl = getBlobURL(xhr2.response);
                img.onload = function () {
                    cntx.drawImage(img, 0, 0, cvs.width, cvs.height);
                    var b64String = cvs.toDataURL('image/png');
                    revokeBlobURL(bUrl);
                    var loc = b64String.search('base64,');
                    var retstring = b64String.slice(loc + 7);
                    if (callback) {
                        callback(retstring);
                    }
                };
                img.src = bUrl;
            } else {
                if (callback) callback('');
            }
        };
        xhr2.onerror = function () {
            if (callback) callback('');
        };
        xhr2.send(null);
    }

    // Exponer API global
    window.IsSigWebInstalled = isSigWebInstalled;
    window.SetDisplayTarget = setDisplayTarget;
    window.SetTabletState = setTabletState;
    window.GetTabletState = getTabletState;
    window.Reset = resetTablet;
    window.ClearTablet = clearTablet;
    window.SetImageXSize = setImageXSize;
    window.SetImageYSize = setImageYSize;
    window.SetImagePenWidth = setImagePenWidth;
    window.GetImageXSize = getImageXSize;
    window.GetImageYSize = getImageYSize;
    window.SigWebRefresh = sigWebRefresh;
    window.GetSigImageB64 = getSigImageB64;

})(window);
