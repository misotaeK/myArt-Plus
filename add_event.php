<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['lang'])) $_SESSION['lang'] = 'en';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en'])) $_SESSION['lang'] = $_GET['lang'];
$current_lang = $_SESSION['lang'];
require_once "lang/" . $current_lang . ".php";

if (!isset($_SESSION['theme'])) $_SESSION['theme'] = 'light';
if (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark'])) $_SESSION['theme'] = $_GET['theme'];
$current_theme = $_SESSION['theme'];

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

// events tablosunda location kolonu yoksa ekle (eski kurulumlarla uyumluluk için)
$loc_col_check = mysqli_query($conn, "SHOW COLUMNS FROM events LIKE 'location'");
if ($loc_col_check && mysqli_num_rows($loc_col_check) === 0) {
    mysqli_query($conn, "ALTER TABLE events ADD COLUMN location VARCHAR(255) DEFAULT NULL");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if ($title === '' || $event_date === '') {
        $error = 'missing_fields';
    } else {
        $poster = null;
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['poster']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $new_filename = "event_" . time() . "_" . basename($filename);
                $target_file = $target_dir . $new_filename;
                if (move_uploaded_file($_FILES['poster']['tmp_name'], $target_file)) {
                    $poster = $target_file;
                }
            }
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO events (creator_id, title, description, event_date, poster_image, location) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssss", $user_id, $title, $description, $event_date, $poster, $location);
        mysqli_stmt_execute($stmt);
        $new_event_id = mysqli_insert_id($conn);

        header("Location: event.php?id=" . $new_event_id);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <title>myArt+ | <?php echo $t['add_event']; ?></title>
    <link rel="stylesheet" href="style.css?v=27">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .main-wrapper {
            background: transparent;
        }

        .event-form-row {
            padding: 10px 15px;
        }

        .event-form-row label {
            display: block;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 4px;
            color: var(--footer-text);
        }

        .dtpicker {
            position: relative;
        }

        .dtpicker-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            text-align: left;
            font-family: inherit;
        }

        .dtpicker-trigger .placeholder {
            color: var(--footer-text);
        }

        .dtpicker-trigger.dtpicker-error {
            border-color: #cc0000;
        }

        .dtpicker-popup {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            z-index: 50;
            width: 240px;
            background: var(--box-bg);
            border: 1px dotted var(--link-color);
            box-shadow: 2px 2px 0 var(--shadow-color);
            padding: 10px;
            box-sizing: border-box;
        }

        .dtpicker-popup.open {
            display: block;
        }

        .dtpicker-cal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: bold;
            color: var(--text-color);
        }

        .dtpicker-nav {
            width: 22px;
            height: 22px;
            border: 1px solid var(--border-color);
            background: var(--thumb-bg);
            color: var(--text-color);
            cursor: pointer;
            font-weight: bold;
            font-size: 11px;
            padding: 0;
        }

        .dtpicker-nav:hover {
            background: #76e2da;
            border-color: #76e2da;
        }

        .dtpicker-weekdays,
        .dtpicker-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }

        .dtpicker-weekdays {
            font-size: 9px;
            font-weight: bold;
            color: var(--footer-text);
            text-align: center;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .dtpicker-day {
            aspect-ratio: 1;
            width: 100%;
            border: 1px solid transparent;
            background: transparent;
            color: var(--text-color);
            font-size: 11px;
            cursor: pointer;
            padding: 0;
        }

        .dtpicker-day:hover {
            border-color: #76e2da;
        }

        .dtpicker-day.outside {
            color: var(--footer-text);
            opacity: 0.5;
        }

        .dtpicker-day.today {
            border-color: #76e2da;
            font-weight: bold;
        }

        .dtpicker-day.selected {
            background: #76e2da;
            color: #0b3d3a;
            font-weight: bold;
            border-color: #76e2da;
        }

        .dtpicker-time-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin: 10px 0 8px;
        }

        .dtpicker-time-input {
            width: 42px;
            box-sizing: border-box;
            text-align: center;
            background: var(--bg-color);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 4px;
            font-size: 12px;
        }

        .dtpicker-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .dtpicker-link-btn {
            background: none;
            border: none;
            color: var(--link-color);
            font-size: 10px;
            font-weight: bold;
            text-decoration: underline;
            cursor: pointer;
            padding: 4px 0;
            white-space: nowrap;
        }

        .dtpicker-actions .form-btn {
            margin-left: auto;
        }
    </style>
</head>

<body class="<?php echo $current_theme; ?>">
<div class="marquee-wrap"><div class="marquee-text">★ WELCOME TO MYART+ ★ SHARE YOUR ART WITH THE WORLD ★ JOIN THE FORUM ★ NEW EVENTS POSTED WEEKLY ★</div></div>
    <table width="960" border="0" cellpadding="0" cellspacing="0" align="center" class="main-wrapper">
        <tr height="35">
            <td>
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" align="left" valign="bottom"><a href="index.php"><img src="logo.png" alt="Logo" border="0" class="site-logo"></a></td>
                        <td width="50%" align="right" valign="top" class="top-controls" style="padding-top: 5px; font-size:12px;">
                            <?php echo $t['theme']; ?>
                            <?php if ($current_theme == 'light'): ?><span class="active"><?php echo $t['light']; ?></span> | <a href="?theme=dark"><?php echo $t['dark']; ?></a><?php else: ?><a href="?theme=light"><?php echo $t['light']; ?></a> | <span class="active"><?php echo $t['dark']; ?></span><?php endif; ?> &nbsp;&nbsp;&nbsp;
                            <?php echo $t['lang_label']; ?>
                            <?php if ($current_lang == 'en'): ?><a href="?lang=tr">TR</a> | <span class="active">EN</span><?php else: ?><span class="active">TR</span> | <a href="?lang=en">EN</a><?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr height="30">
            <td>
                <?php $current_page = 'events'; include 'navbar.php'; ?>
            </td>
        </tr>
        <tr>
            <td valign="top" style="padding: 10px 8px;">
                <div class="box">
                    <div class="header-blue" style="display:flex; justify-content:space-between; align-items:center;">
                        <span><?php echo $t['add_event']; ?></span>
                        <a href="events.php" style="color:#7a0044; font-size:11px; text-decoration:underline;"><?php echo $t['back_to_events']; ?></a>
                    </div>

                    <?php if ($error === 'missing_fields'): ?>
                        <div style="padding:10px 15px; color:#c0392b; font-size:12px;"><?php echo htmlspecialchars($t['form_required_fields']); ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="event-form-row">
                            <label><?php echo $t['event_title_placeholder']; ?></label>
                            <input type="text" name="title" class="form-input" maxlength="150" required value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                        </div>
                        <div class="event-form-row">
                            <label><?php echo $t['event_description_placeholder']; ?></label>
                            <textarea name="description" class="form-input" rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        <div class="event-form-row">
                            <label><?php echo $t['event_date_label']; ?></label>
                            <div class="dtpicker" id="eventDatePicker">
                                <button type="button" class="dtpicker-trigger form-input" id="dtpickerTrigger">
                                    <span id="dtpickerTriggerText" class="placeholder"><?php echo htmlspecialchars($t['dtpicker_placeholder']); ?></span>
                                </button>
                                <input type="hidden" name="event_date" id="eventDateHidden" value="<?php echo isset($_POST['event_date']) ? htmlspecialchars($_POST['event_date']) : ''; ?>">
                                <div class="dtpicker-popup" id="dtpickerPopup">
                                    <div class="dtpicker-cal-header">
                                        <button type="button" class="dtpicker-nav" id="dtpickerPrev">&lt;</button>
                                        <span id="dtpickerMonthLabel"></span>
                                        <button type="button" class="dtpicker-nav" id="dtpickerNext">&gt;</button>
                                    </div>
                                    <div class="dtpicker-weekdays" id="dtpickerWeekdays"></div>
                                    <div class="dtpicker-days" id="dtpickerDays"></div>
                                    <div class="dtpicker-time-row">
                                        <input type="number" id="dtpickerHour" min="0" max="23" placeholder="HH" class="dtpicker-time-input">
                                        <span>:</span>
                                        <input type="number" id="dtpickerMinute" min="0" max="59" placeholder="MM" class="dtpicker-time-input">
                                    </div>
                                    <div class="dtpicker-actions">
                                        <button type="button" id="dtpickerClear" class="dtpicker-link-btn"><?php echo htmlspecialchars($t['dtpicker_clear']); ?></button>
                                        <button type="button" id="dtpickerToday" class="dtpicker-link-btn"><?php echo htmlspecialchars($t['dtpicker_today']); ?></button>
                                        <button type="button" id="dtpickerDone" class="form-btn"><?php echo htmlspecialchars($t['dtpicker_done']); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="event-form-row">
                            <label><?php echo $t['event_location_label']; ?></label>
                            <input type="text" name="location" class="form-input" maxlength="255" placeholder="<?php echo htmlspecialchars($t['event_location_placeholder']); ?>" value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                        </div>
                        <div class="event-form-row">
                            <label><?php echo $t['event_poster_label']; ?></label>
                            <input type="file" name="poster" accept=".jpg,.jpeg,.png,.gif">
                            <div class="size-hint"><?php echo $t['event_poster_size_hint']; ?></div>
                        </div>
                        <div class="event-form-row">
                            <button type="submit" class="form-btn"><?php echo $t['create_event']; ?></button>
                        </div>
                    </form>
                </div>
            </td>
        </tr>
        <tr>
            <td valign="bottom" style="padding-top: 10px;">
                <div class="footer" style="text-align:center; padding:10px; font-size:12px;">
                    <a href="qa.php"><?php echo $t['qa']; ?></a> | <a href="privacy.php"><?php echo $t['privacy']; ?></a> | <a href="help.php"><?php echo $t['help']; ?></a> | <a href="terms.php"><?php echo $t['terms']; ?></a>
                    <div class="footer-copy" style="margin-top:5px; color:gray;">© <?php echo date("Y"); ?> myArt+ | <?php echo $t['all_rights_reserved']; ?></div>
                </div>
            </td>
        </tr>
    </table>

    <script>
        (function() {
            const MONTHS = <?php echo json_encode(explode(',', $t['dtpicker_months'])); ?>;
            const WEEKDAYS = <?php echo json_encode(explode(',', $t['dtpicker_weekdays'])); ?>;
            const PLACEHOLDER_TEXT = <?php echo json_encode($t['dtpicker_placeholder']); ?>;

            const wrap = document.getElementById('eventDatePicker');
            const trigger = document.getElementById('dtpickerTrigger');
            const triggerText = document.getElementById('dtpickerTriggerText');
            const hidden = document.getElementById('eventDateHidden');
            const popup = document.getElementById('dtpickerPopup');
            const monthLabel = document.getElementById('dtpickerMonthLabel');
            const weekdaysEl = document.getElementById('dtpickerWeekdays');
            const daysEl = document.getElementById('dtpickerDays');
            const hourInput = document.getElementById('dtpickerHour');
            const minuteInput = document.getElementById('dtpickerMinute');

            function pad(n) {
                return String(n).padStart(2, '0');
            }

            function parseValue(v) {
                const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(v || '');
                if (!m) return null;
                return { y: +m[1], mo: +m[2] - 1, d: +m[3], h: +m[4], mi: +m[5] };
            }

            const now = new Date();
            let selectedDate = parseValue(hidden.value);
            let viewYear = selectedDate ? selectedDate.y : now.getFullYear();
            let viewMonth = selectedDate ? selectedDate.mo : now.getMonth();

            weekdaysEl.innerHTML = WEEKDAYS.map(function(w) { return '<span>' + w + '</span>'; }).join('');

            if (selectedDate) {
                hourInput.value = pad(selectedDate.h);
                minuteInput.value = pad(selectedDate.mi);
            }

            function formatDisplay(sel) {
                if (!sel) return null;
                return pad(sel.d) + '.' + pad(sel.mo + 1) + '.' + sel.y + '  ' + pad(sel.h) + ':' + pad(sel.mi);
            }

            function formatValue(sel) {
                if (!sel) return '';
                return sel.y + '-' + pad(sel.mo + 1) + '-' + pad(sel.d) + 'T' + pad(sel.h) + ':' + pad(sel.mi);
            }

            function updateTrigger() {
                const text = formatDisplay(selectedDate);
                if (text) {
                    triggerText.textContent = text;
                    triggerText.classList.remove('placeholder');
                } else {
                    triggerText.textContent = PLACEHOLDER_TEXT;
                    triggerText.classList.add('placeholder');
                }
            }

            function commitValue() {
                hidden.value = formatValue(selectedDate);
                updateTrigger();
            }

            function makeDayCell(day, isOutside, m, y) {
                let mm = m, yy = y;
                if (mm < 0) { mm = 11; yy--; }
                if (mm > 11) { mm = 0; yy++; }

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = day;
                btn.className = 'dtpicker-day' + (isOutside ? ' outside' : '');

                if (yy === now.getFullYear() && mm === now.getMonth() && day === now.getDate()) {
                    btn.classList.add('today');
                }
                if (selectedDate && yy === selectedDate.y && mm === selectedDate.mo && day === selectedDate.d) {
                    btn.classList.add('selected');
                }

                btn.addEventListener('click', function() {
                    const h = selectedDate ? selectedDate.h : (parseInt(hourInput.value, 10) || 12);
                    const mi = selectedDate ? selectedDate.mi : (parseInt(minuteInput.value, 10) || 0);
                    selectedDate = { y: yy, mo: mm, d: day, h: h, mi: mi };
                    viewYear = yy;
                    viewMonth = mm;
                    hourInput.value = pad(h);
                    minuteInput.value = pad(mi);
                    renderCalendar();
                });

                return btn;
            }

            function renderCalendar() {
                monthLabel.textContent = MONTHS[viewMonth] + ' ' + viewYear;
                daysEl.innerHTML = '';

                const firstOfMonth = new Date(viewYear, viewMonth, 1);
                const startOffset = (firstOfMonth.getDay() + 6) % 7;
                const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
                const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();

                for (let i = startOffset; i > 0; i--) {
                    daysEl.appendChild(makeDayCell(daysInPrevMonth - i + 1, true, viewMonth - 1, viewYear));
                }
                for (let d = 1; d <= daysInMonth; d++) {
                    daysEl.appendChild(makeDayCell(d, false, viewMonth, viewYear));
                }
                const totalCells = startOffset + daysInMonth;
                const trailing = (7 - (totalCells % 7)) % 7;
                for (let d = 1; d <= trailing; d++) {
                    daysEl.appendChild(makeDayCell(d, true, viewMonth + 1, viewYear));
                }
            }

            function syncTimeFromInputs() {
                if (!selectedDate) return;
                let h = parseInt(hourInput.value, 10);
                let mi = parseInt(minuteInput.value, 10);
                if (isNaN(h) || h < 0) h = 0;
                if (h > 23) h = 23;
                if (isNaN(mi) || mi < 0) mi = 0;
                if (mi > 59) mi = 59;
                selectedDate.h = h;
                selectedDate.mi = mi;
            }

            hourInput.addEventListener('change', syncTimeFromInputs);
            minuteInput.addEventListener('change', syncTimeFromInputs);

            document.getElementById('dtpickerPrev').addEventListener('click', function() {
                viewMonth--;
                if (viewMonth < 0) { viewMonth = 11; viewYear--; }
                renderCalendar();
            });

            document.getElementById('dtpickerNext').addEventListener('click', function() {
                viewMonth++;
                if (viewMonth > 11) { viewMonth = 0; viewYear++; }
                renderCalendar();
            });

            document.getElementById('dtpickerClear').addEventListener('click', function() {
                selectedDate = null;
                hourInput.value = '';
                minuteInput.value = '';
                renderCalendar();
            });

            document.getElementById('dtpickerToday').addEventListener('click', function() {
                const h = selectedDate ? selectedDate.h : now.getHours();
                const mi = selectedDate ? selectedDate.mi : now.getMinutes();
                selectedDate = { y: now.getFullYear(), mo: now.getMonth(), d: now.getDate(), h: h, mi: mi };
                viewYear = selectedDate.y;
                viewMonth = selectedDate.mo;
                hourInput.value = pad(h);
                minuteInput.value = pad(mi);
                renderCalendar();
            });

            function openPopup() {
                popup.classList.add('open');
                renderCalendar();
            }

            function closePopup() {
                popup.classList.remove('open');
                syncTimeFromInputs();
                commitValue();
            }

            trigger.addEventListener('click', function() {
                if (popup.classList.contains('open')) {
                    closePopup();
                } else {
                    openPopup();
                }
            });

            document.getElementById('dtpickerDone').addEventListener('click', closePopup);

            document.addEventListener('click', function(e) {
                if (popup.classList.contains('open') && !wrap.contains(e.target)) {
                    closePopup();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && popup.classList.contains('open')) {
                    closePopup();
                }
            });

            wrap.closest('form').addEventListener('submit', function(e) {
                if (!hidden.value) {
                    e.preventDefault();
                    trigger.classList.add('dtpicker-error');
                    openPopup();
                }
            });

            trigger.addEventListener('click', function() {
                trigger.classList.remove('dtpicker-error');
            });

            updateTrigger();
        })();
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php include 'chat_widget.php'; ?>
</body>

</html>
