/* 网站js */
$(function () {
    //搜索（回车或点击按钮）
    function doSearch() {
        var keyword = $.trim($('.search-input').val() || '');
        if (keyword.length > 0) {
            location.href = window.__site_home__ + 'e/search?kwd=' + encodeURIComponent(keyword);
        }
    }

    document.onkeydown = function (ev) {
        var event = ev || event;
        if (event.keyCode == 13) {
            doSearch();
        }
    }

    $('.search-box .search-btn').click(function () {
        doSearch();
    });

    //顶部日期
    var now = new Date();
    var week = ['日', '一', '二', '三', '四', '五', '六'][now.getDay()];
    var pad = function (n) { return n < 10 ? '0' + n : n; };
    var dateText = now.getFullYear() + '年' + pad(now.getMonth() + 1) + '月' + pad(now.getDate()) + '日' + ' 星期' + week;
    var $date = $('.topbar-date');
    if ($date.length) {
        $date.text(dateText);
    }

    //返回顶部
    var $backTop = $('.back-top');
    if ($backTop.length) {
        $(window).on('scroll', function () {
            if ($(window).scrollTop() > 300) {
                $backTop.addClass('show');
            } else {
                $backTop.removeClass('show');
            }
        });
        $backTop.click(function () {
            $('html, body').animate({ scrollTop: 0 }, 300);
        });
    }
});
