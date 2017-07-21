if (typeof _payapi == "undefined") {
    var _payapi = {};
}
var __isProductPage = true;
_payapi.checkMandatory = function _checkMandatory(url, silencePopup, msg) {
    var isValid = isValidForm(silencePopup);
    console.log("Is valid: " + isValid);
    if (!isValid) {
        return '';
    }
    //Add product options to the currUrl
    var $toCartForm = $("form.cart");
    var opts = decodeURIComponent($toCartForm.find(":input:not(:hidden)").serialize());
    var separator = "&";
    if (url.indexOf("?") < 0) {
        separator = "?";
    }
    if (silencePopup) {
        return url + separator + opts;
    }
    var productUrl = baseUrl;
    productUrl = productUrl + encodeURIComponent(url + separator + opts);
    window.location = productUrl;
};

function isValidForm(silencePopup) {
    var msg = "";
    var $toCartForm = $("form.cart");
    var $addToCartBtn = $(".single_add_to_cart_button");
    if ($addToCartBtn.is('.disabled')) {
        if ($addToCartBtn.is('.wc-variation-is-unavailable')) {
            msg = wc_add_to_cart_variation_params.i18n_unavailable_text;
        } else if ($addToCartBtn.is('.wc-variation-selection-needed')) {
            msg = wc_add_to_cart_variation_params.i18n_make_a_selection_text;
        }
    }
    if (msg == "") {
        //Check stock
        var $qtyField = $("input[name='quantity']");
        var minStock = parseInt($qtyField.attr('min'));
        var maxStock = parseInt($qtyField.attr('max'));
        var qty = parseInt($qtyField.val());
        if (minStock > qty || maxStock < qty) {
            msg = "No stock";
            $addToCartBtn.click();
            return false;
        }
    }
    if ($addToCartBtn.length == 0) {
        msg = "Product out of stock";
    }
    if (msg != "" && !silencePopup) {
        alert(msg);
    }
    return (msg == "");
}
$(document).ready(function() {
    var $btnClicked = $("#payapi-button");
    if ($("button.single_add_to_cart_button").length > 0 && $btnClicked.closest(".outofstock").length == 0){        
        $(".fngrsharesdk-container").addClass("fngrsharesdk-productpage");
        $btnClicked.click(function(evt) {
            _payapi.checkMandatory(location.href, false);
        });
    } else {
        $btnClicked.remove();
    }
});