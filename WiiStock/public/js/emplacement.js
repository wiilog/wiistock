    /* Emplacements
    ================= */

    var l_emplacement;

    var toggleType = false;
    $('.switch-type').on('click', function () {
        var toggleSwitch = $('.switch-type #toggle-switch');
        toggleType = !toggleType;
        if (toggleType) {
            toggleSwitch.css('clipPath', 'inset(0 0 0 50%)');
            toggleSwitch.css('backgroundColor', '#ffbc02');
            $('.manuel .zone').show();
            $('.manuel .stock').hide();
        } else {
            toggleSwitch.css('clipPath', 'inset(0 50% 0 0)');
            toggleSwitch.css('backgroundColor', '#130078');
            $('.manuel .zone').hide();
            $('.manuel .stock').show();
        }
    });

    function isEmpty(obj) {
        for (var key in obj) {
            if (obj.hasOwnProperty(key)) 
                return false;
            }
        return true;
    }

    function parseEmplacements(entrepots) {
        var data = new Array(6);
        var a,
            t,
            r,
            e,
            z;
        a = t = r = e = z = 0;
        $.each(entrepots, function (i, val) {
            if (!data[0]) 
                data[0] = [];
            var allees = entrepots[i].allees;
            var zones = entrepots[i].zones;
            data[0][i] = [
                -1, entrepots[i]
            ];
            $.each(zones, function (j, val) {
                if (!data[5]) 
                    data[5] = [];
                data[5][z++] = [
                    entrepots[i].id,
                    zones[j]
                ];
            });
            $.each(allees, function (j, val) {
                if (!data[1]) 
                    data[1] = [];
                var travees = allees[j].travees;
                data[1][a++] = [
                    entrepots[i].id,
                    allees[j]
                ];
                $.each(travees, function (k, val) {
                    if (!data[2]) 
                        data[2] = [];
                    var racks = travees[k].racks;
                    data[2][t++] = [
                        allees[j].id,
                        travees[k]
                    ];
                    $.each(racks, function (l, val) {
                        if (!data[3]) 
                            data[3] = [];
                        var emplacements = racks[l].emplacements;
                        data[3][r++] = [
                            travees[k].id,
                            racks[l]
                        ];
                        $.each(emplacements, function (m, val) {
                            if (!data[4]) 
                                data[4] = [];
                            data[4][e++] = [
                                racks[l].id,
                                emplacements[m]
                            ];
                        });
                    });
                });
            });
        });
        return data;
    }

    function getJsonFromArray(array) {
        var data = [];
        if (array && !isEmpty(array)) {
            $.each(array, function (i) {
                item = {};
                item["id"] = array[i].id;
                item["text"] = array[i].nom;
                data.push(item);
            });
            return data;
        } else {
            item = {};
            item["id"] = -1;
            item["text"] = 'vide';
            data.push(item);
            return data;
        }
    }

    function getEntrepots() {
        var data = [];

        $.each(l_emplacement[0], function (i, val) {
            data.push(l_emplacement[0][i][1]);
        });
        return data;
    }

    function getZones(entrepot) {
        var data = [];
        if (entrepot == "") {
            return data;
        } else {
            $.each(l_emplacement[5], function (i, val) {
                if (l_emplacement[5][i][0] == entrepot) {
                    data.push(l_emplacement[5][i][1]);
                }
            });
            return data;
        }
    }

    function getAllees(entrepot) {
        var data = [];
        if (entrepot == "") {
            return data;
        } else {
            $.each(l_emplacement[1], function (i, val) {
                if (l_emplacement[1][i][0] == entrepot) {
                    data.push(l_emplacement[1][i][1]);
                }
            });
            return data;
        }
    }

    function getTravees(allee) {
        var data = [];
        if (allee == "") {
            return data;
        } else {
            $.each(l_emplacement[2], function (i, val) {
                if (l_emplacement[2][i][0] == allee) {
                    data.push(l_emplacement[2][i][1]);
                }
            });
            return data;
        }
    }

    function getRacks(travee) {
        var data = [];
        if (travee == "") {
            return data;
        } else {
            $.each(l_emplacement[3], function (i, val) {
                if (l_emplacement[3][i][0] == travee) {
                    data.push(l_emplacement[3][i][1]);
                }
            });
            return data;
        }
    }

    function getEmplacements(rack) {
        var data = [];
        if (rack == "") {
            return data;
        } else {
            $.each(l_emplacement[4], function (i, val) {
                if (l_emplacement[4][i][0] == rack) {
                    data.push(l_emplacement[4][i][1]);
                }
            });
            return data;
        }
    }

    function updateEntrepot() {
        $(".js-basic-single-allee").empty().select2({
            data: getJsonFromArray(getAllees(parseInt($(".js-basic-single-entrepot").val())))
        });
        $(".js-basic-single-zone").empty().select2({
            data: getJsonFromArray(getZones(parseInt($(".js-basic-single-entrepot").val())))
        });
        updateAllee();
    }

    function updateAllee() {
        $(".js-basic-single-travee").empty().select2({
            data: getJsonFromArray(getTravees(parseInt($(".js-basic-single-allee").val())))
        });
        updateTravee()
    }

    function updateTravee() {
        $(".js-basic-single-rack").empty().select2({
            data: getJsonFromArray(getRacks(parseInt($(".js-basic-single-travee").val())))
        });
        updateRack()
    }

    function updateRack() {
        $(".js-basic-single-emplacement").empty().select2({
            data: getJsonFromArray(getEmplacements(parseInt($(".js-basic-single-rack").val())))
        });
    }