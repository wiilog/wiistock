$(document).ready(function () {

    $( ".datepicker" ).datepicker({
        language: 'fr',
		format: 'dd/mm/yyyy',
		todayBtn: true,
		todayHighlight: true,
		autoclose: true,
    });

    /**
     * menu
     */
    (function () {

        $(function () {
            var $doc, $has_submenu, $no_submenu, $sidemenu, $slidemenu_bg, hide, move, removeActive;
            $doc = $(document);
            $slidemenu_bg = $('#sidemenu-bg');
            $sidemenu = $('#sidemenu');
            $has_submenu = $sidemenu.find('> li.has-submenu');
            $no_submenu = $sidemenu.find('> li:not(.has-submenu)');
            removeActive = null;
            move = {
                up: $('#move-up'),
                down: $('#move-down'),
                last: $doc.scrollTop()
            };
            move.up.click(function () {
                move.last = $doc.scrollTop();
                $doc.scrollTop(0);
                $doc.scroll();
                return move.down.removeClass('hide');
            });
            move.down.click(function () {
                $doc.scrollTop(move.last);
                $doc.scroll();
                move.up.removeClass('hide');
                return move.down.addClass('hide');
            });
            $doc.scroll(function () {
                if ($doc.scrollTop() > 0) {
                    move.up.removeClass('hide');
                    return move.down.addClass('hide');
                } else {
                    return move.up.addClass('hide');
                }
            });
            $has_submenu.hover(function () {
                var $t;
                $t = $(this);
                $has_submenu.removeClass('active');
                $t.addClass('active');
                $slidemenu_bg.removeClass('hide');
                return clearInterval(removeActive);
            }, function () {
                var $t;
                $t = $(this);
                return removeActive = setTimeout((function () {
                    $t.removeClass('active');
                    return $slidemenu_bg.addClass('hide');
                }), 100);
            });
            hide = function () {
                $slidemenu_bg.addClass('hide');
                return $has_submenu.removeClass('active');
            };
            $slidemenu_bg.click(hide);
            return $no_submenu.hover(hide);
        });

    }).call(this);

    /**
    * Chart
    */
    var randomScalingFactor = function() {
        return Math.round(Math.random() * 100);
    };

    var ctx = document.getElementById("graph").getContext('2d');
    var graph = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"],
            datasets: [{
                label: 'Entrée',
                fill: false,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255,99,132,1)',
                data: [
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor()
                ]
            }, {
                label: 'Sortie',
                fill: false,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                data: [
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor()
                ],
            }]
        },
        options: {
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
});