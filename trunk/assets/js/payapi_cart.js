(function($) {
    $(document).ready(function() {
        setTimeout(function(){
        console.log("INCLUDE CLICK EVENT!!!!!");
        $(".cart-instantbuy-button").click(function(e) {
            var jsonPostData = {};
            $.ajax({
                showLoader: true,
                type: "POST",
                url: "/wp-json/payapi-gateway/v1/secureformgenerator",
                data: jsonPostData,
                success: function(object) {
                    var form = document.createElement('form');
                    form.style.display = 'none';
                    form.setAttribute('method', 'POST');
                    form.setAttribute('action', object.url);
                    form.setAttribute('enctype', 'application/json');
                    var input = document.createElement('input');
                    input.name = 'data';
                    input.type = 'text';
                    input.setAttribute('value', object.data);
                    form.appendChild(input);
                    document.getElementsByTagName('body')[0].appendChild(form);
                    form.submit();
                }
            });
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        });

    },500);
    });
})(jQuery);