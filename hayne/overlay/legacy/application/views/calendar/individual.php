<?php
/**
 * HAYNE presentation of the individual leave calendar.
 * FullCalendar sources, event actions, half-day rendering and ICS behavior remain unchanged.
 */
?>

<main class="hayne-calendar-page" data-hayne-view="calendar-individual-v1">
    <header class="hayne-calendar-header">
        <div>
            <h1>Kalendarz</h1>
            <p>Przeglądaj swoje urlopy, inne nieobecności i dni wolne w jednym miejscu.</p>
        </div>
        <a class="btn hayne-calendar-create" href="<?php echo base_url(); ?>leaves/create">Nowy wniosek</a>
    </header>

    <section class="hayne-calendar-card" aria-label="Kalendarz nieobecności">
        <div class="hayne-calendar-toolbar">
            <div class="hayne-calendar-nav" aria-label="Nawigacja kalendarza">
                <button id="cmdPrevious" class="hayne-calendar-nav__arrow" type="button" aria-label="Poprzedni miesiąc">
                    <span aria-hidden="true">‹</span>
                </button>
                <button id="cmdToday" class="hayne-calendar-today" type="button">Dzisiaj</button>
                <button id="cmdNext" class="hayne-calendar-nav__arrow" type="button" aria-label="Następny miesiąc">
                    <span aria-hidden="true">›</span>
                </button>
            </div>

            <div class="hayne-calendar-tools">
                <button id="cmdDisplayDayOff" class="hayne-calendar-tool" type="button" aria-pressed="false">
                    <span class="hayne-calendar-tool__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                            <path d="M16 3v4M8 3v4M3 10h18"></path>
                        </svg>
                    </span>
                    <span>Dni wolne</span>
                </button>
                <?php if ($this->config->item('ics_enabled') == TRUE) { ?>
                    <button id="lnkICS" class="hayne-calendar-tool" type="button">
                        <span class="hayne-calendar-tool__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"></path>
                            </svg>
                        </span>
                        <span>ICS</span>
                    </button>
                <?php } ?>
            </div>
        </div>

        <div class="hayne-calendar-legend" aria-label="Legenda statusów">
            <span class="hayne-calendar-legend__item hayne-calendar-legend__item--planned"><i></i>Plan</span>
            <span class="hayne-calendar-legend__item hayne-calendar-legend__item--accepted"><i></i>Zaakceptowane</span>
            <span class="hayne-calendar-legend__item hayne-calendar-legend__item--requested"><i></i>Oczekujące</span>
            <span class="hayne-calendar-legend__item hayne-calendar-legend__item--rejected"><i></i>Odrzucone</span>
            <span class="hayne-calendar-legend__item hayne-calendar-legend__item--dayoff"><i></i>Dzień wolny</span>
        </div>

        <div class="hayne-calendar-stage">
            <div id="calendar"></div>
        </div>
    </section>
</main>

<div id="frmEvent" class="modal hide fade hayne-calendar-modal">
    <div class="modal-header">
        <a href="#" onclick="$('#frmEvent').modal('hide');" class="close">&times;</a>
        <h3>Nieobecność</h3>
    </div>
    <div class="modal-body">
        <a href="#" id="lnkDownloadCalEvnt">Pobierz wydarzenie iCal</a>
        <p>Możesz dodać tę nieobecność do swojego kalendarza.</p>
    </div>
    <div class="modal-footer">
        <a href="#" onclick="$('#frmEvent').modal('hide');" class="btn">Zamknij</a>
    </div>
</div>

<div class="modal hide hayne-calendar-wait" id="frmModalAjaxWait" data-backdrop="static" data-keyboard="false">
    <div class="modal-body">
        <span class="hayne-calendar-loader" aria-hidden="true"></span>
        <strong>Ładowanie kalendarza…</strong>
    </div>
</div>

<div id="frmLinkICS" class="modal hide fade hayne-calendar-modal">
    <div class="modal-header">
        <h3>Kalendarz ICS<a href="#" onclick="$('#frmLinkICS').modal('hide');" class="close">&times;</a></h3>
    </div>
    <div class="modal-body" id="frmSelectDelegateBody">
        <p>Skopiuj adres i dodaj go jako subskrypcję w swoim kalendarzu.</p>
        <div class="input-append hayne-calendar-ics-control">
            <?php $icsUrl = base_url() . 'ics/individual/' . $user_id . '?token=' . $this->session->userdata('random_hash'); ?>
            <input type="text" class="input-xlarge" id="txtIcsUrl" onfocus="this.select();" onmouseup="return false;"
                value="<?php echo $icsUrl; ?>" />
            <button id="cmdCopy" class="btn" data-clipboard-text="<?php echo $icsUrl; ?>">Kopiuj</button>
            <a href="#" id="tipCopied" data-toggle="tooltip" title="Skopiowano" data-placement="right"
                data-container="#cmdCopy"></a>
        </div>
    </div>
    <div class="modal-footer">
        <a href="#" onclick="$('#frmLinkICS').modal('hide');" class="btn btn-primary">Gotowe</a>
    </div>
</div>

<link href="<?php echo base_url(); ?>assets/fullcalendar-2.8.0/fullcalendar.css" rel="stylesheet">
<script type="text/javascript" src="<?php echo base_url(); ?>assets/fullcalendar-2.8.0/fullcalendar.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/bootbox.min.js"></script>
<script type="text/javascript">
    var hayneWrap = document.getElementById('wrap');
    if (hayneWrap) {
        hayneWrap.setAttribute('data-hayne-topbar-title', 'Kalendarz');
    }

    var toggleDayoffs = false;

    function hayneCalendarEventClass(event) {
        var color = String(event.color || '').toLowerCase();
        if (color === '#999' || color === '#999999') return 'hayne-calendar-event--planned';
        if (color === '#468847') return 'hayne-calendar-event--accepted';
        if (color === '#f89406') return 'hayne-calendar-event--requested';
        if (color === '#000' || color === '#000000') return 'hayne-calendar-event--dayoff';
        return 'hayne-calendar-event--rejected';
    }

    $(function () {
        $(document).ajaxError(function (event, jqXHR, settings, errorThrown) {
            $('#frmModalAjaxWait').modal('hide');
            if (jqXHR.status == 401) {
                bootbox.alert("<?php echo lang('global_ajax_timeout'); ?>", function () {
                    location.reload();
                });
            } else {
                bootbox.alert("<?php echo lang('global_ajax_error'); ?>");
            }
        });

        $("#frmEvent").alert();

        $('#calendar').fullCalendar({
            timeFormat: ' ',
            firstDay: 1,
            monthNames: ['Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'],
            monthNamesShort: ['Sty', 'Lut', 'Mar', 'Kwi', 'Maj', 'Cze', 'Lip', 'Sie', 'Wrz', 'Paź', 'Lis', 'Gru'],
            dayNames: ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'],
            dayNamesShort: ['Nd', 'Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'Sb'],
            header: {
                left: "",
                center: "title",
                right: ""
            },
            events: '<?php echo base_url(); ?>leaves/individual',
            eventClick: function (calEvent, jsEvent, view) {
                if (calEvent.color != '#000000') {
                    var link = "<?php echo base_url(); ?>ics/ical/" + calEvent.id;
                    $("#lnkDownloadCalEvnt").attr('href', link);
                    $('#frmEvent').modal('show');
                }
            },
            loading: function (isLoading) {
                if (isLoading) {
                    $('#frmModalAjaxWait').modal('show');
                } else {
                    $('#frmModalAjaxWait').modal('hide');
                }
            },
            eventRender: function (event, element, view) {
                $(element).addClass(hayneCalendarEventClass(event));
                if (event.imageurl) {
                    $(element).find('span:first').prepend('<img src="' + event.imageurl + '" />');
                }
            },
            eventAfterRender: function (event, element, view) {
                $(element).attr('title', event.title);

                if (event.enddatetype == "Morning" || event.startdatetype == "Afternoon") {
                    var nb_days = event.end.diff(event.start, "days");
                    var duration = 0.5;
                    var halfday_length = 0;
                    var length = 0;
                    var width = parseInt(jQuery(element).css('width'));
                    if (nb_days > 0) {
                        if (event.enddatetype == "Afternoon") {
                            duration = nb_days + 0.5;
                        } else {
                            duration = nb_days;
                        }
                        nb_days++;
                        halfday_length = Math.round((width / nb_days) / 2);
                        if (event.startdatetype == "Afternoon" && event.enddatetype == "Morning") {
                            length = width - (halfday_length * 2);
                        } else {
                            length = width - halfday_length;
                        }
                    } else {
                        halfday_length = Math.round(width / 2);
                        length = halfday_length;
                    }
                    $(element).css('width', length + "px");

                    if (event.startdatetype == "Afternoon") {
                        $(element).css('margin-left', halfday_length + "px");
                    }
                }
            },
            windowResize: function (view) {
                $('#calendar').fullCalendar('rerenderEvents');
            }
        });

        $('#frmEvent').on('hidden', function () {
            $(this).removeData('modal');
        });

        $('#cmdDisplayDayOff').on('click', function () {
            toggleDayoffs = !toggleDayoffs;
            $(this).toggleClass('is-active', toggleDayoffs).attr('aria-pressed', toggleDayoffs ? 'true' : 'false');
            if (toggleDayoffs) {
                $('#calendar').fullCalendar('addEventSource', '<?php echo base_url(); ?>contracts/calendar/userdayoffs');
            } else {
                $('#calendar').fullCalendar('removeEventSources', '<?php echo base_url(); ?>contracts/calendar/userdayoffs');
            }
        });

        $('#cmdNext').click(function () {
            $('#calendar').fullCalendar('next');
        });
        $('#cmdPrevious').click(function () {
            $('#calendar').fullCalendar('prev');
        });

        $('#cmdToday').click(function () {
            var displayedDate = new Date($('#calendar').fullCalendar('getDate'));
            var currentDate = new Date();
            if (displayedDate.getMonth() == currentDate.getMonth()) {
                $('#calendar').fullCalendar('refetchEvents');
            } else {
                $('#calendar').fullCalendar('today');
            }
        });

        var client = new ClipboardJS("#cmdCopy");
        $('#lnkICS').click(function () {
            $('#frmLinkICS').modal('show');
        });
        client.on("success", function () {
            $('#tipCopied').tooltip('show');
            setTimeout(function () { $('#tipCopied').tooltip('hide') }, 1000);
        });
    });
</script>
