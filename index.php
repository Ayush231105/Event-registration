<?php
// ============================================================
// EVENT REGISTRATION PORTAL - Single File
// ============================================================

// -------------------- DATABASE CONFIG --------------------
$host     = 'localhost';
$dbname   = 'event_registration';
$username = 'root';          // change if needed
$password = '';              // change if needed

// -------------------- CONNECT & CREATE DB/TABLES --------------------
try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Create events table
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        description TEXT,
        event_date DATE NOT NULL,
        location VARCHAR(150),
        seats INT DEFAULT 100,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create registrations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(120) NOT NULL,
        phone VARCHAR(20),
        tickets INT DEFAULT 1,
        registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        UNIQUE KEY unique_email_event (email, event_id)
    )");

    // Insert sample events if table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO events (title, description, event_date, location, seats) VALUES
            ('Tech Conference 2026', 'Annual technology conference with keynote speakers and workshops.', '2026-11-15', 'Bangalore Convention Center', 200),
            ('Music Festival', 'Live performances by top artists. Food stalls and camping available.', '2026-12-05', 'Open Grounds, Hyderabad', 500),
            ('Startup Meetup', 'Networking event for founders, investors and developers.', '2026-10-28', 'WeWork, Mumbai', 80),
            ('AI Workshop', 'Hands-on workshop on Generative AI and LLMs.', '2026-11-02', 'Online (Zoom)', 150)
        ");
    }

} catch (PDOException $e) {
    die("<h2 style='color:red;text-align:center;margin-top:50px'>Database Error: " . $e->getMessage() . "</h2>
         <p style='text-align:center'>Please check your MySQL credentials at the top of the file.</p>");
}

// -------------------- HANDLE FORM SUBMISSION --------------------
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $event_id   = (int)$_POST['event_id'];
    $full_name  = trim($_POST['full_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $tickets    = max(1, (int)$_POST['tickets']);

    // Basic validation
    if (empty($full_name) || empty($email) || empty($event_id)) {
        $message = "Please fill all required fields.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address.";
        $messageType = "error";
    } else {
        try {
            // Check available seats
            $stmt = $pdo->prepare("SELECT seats FROM events WHERE id = ?");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                $message = "Selected event not found.";
                $messageType = "error";
            } else {
                // Count already registered tickets
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(tickets),0) FROM registrations WHERE event_id = ?");
                $stmt->execute([$event_id]);
                $booked = $stmt->fetchColumn();

                if ($booked + $tickets > $event['seats']) {
                    $message = "Not enough seats available. Only " . ($event['seats'] - $booked) . " left.";
                    $messageType = "error";
                } else {
                    // Insert registration
                    $stmt = $pdo->prepare("INSERT INTO registrations (event_id, full_name, email, phone, tickets) 
                                           VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$event_id, $full_name, $email, $phone, $tickets]);

                    $message = "Registration successful! You have booked $tickets ticket(s).";
                    $messageType = "success";
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "You have already registered for this event with this email.";
            } else {
                $message = "Error: " . $e->getMessage();
            }
            $messageType = "error";
        }
    }
}

// -------------------- FETCH DATA --------------------
$events = $pdo->query("SELECT e.*, 
    (SELECT COALESCE(SUM(tickets),0) FROM registrations r WHERE r.event_id = e.id) AS booked
    FROM events e ORDER BY e.event_date ASC")->fetchAll(PDO::FETCH_ASSOC);

$registrations = $pdo->query("SELECT r.*, e.title AS event_title 
    FROM registrations r 
    JOIN events e ON r.event_id = e.id 
    ORDER BY r.registered_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background: #0f172a; color: #e2e8f0; min-height: 100vh; }

        header {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        header h1 { font-size: 2rem; font-weight: 700; }
        header p { opacity: 0.9; margin-top: 6px; }

        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }

        .message {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            text-align: center;
        }
        .message.success { background: #065f46; color: #a7f3d0; border: 1px solid #10b981; }
        .message.error   { background: #7f1d1d; color: #fecaca; border: 1px solid #ef4444; }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        @media (max-width: 850px) { .grid { grid-template-columns: 1fr; } }

        .card {
            background: #1e293b;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            border: 1px solid #334155;
        }
        .card h2 {
            font-size: 1.4rem;
            margin-bottom: 20px;
            color: #a5b4fc;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Events List */
        .event-item {
            background: #0f172a;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 14px;
            border-left: 4px solid #6366f1;
            transition: transform 0.2s;
        }
        .event-item:hover { transform: translateX(4px); }
        .event-item h3 { font-size: 1.1rem; color: #f1f5f9; margin-bottom: 6px; }
        .event-item p { font-size: 0.9rem; color: #94a3b8; margin: 3px 0; }
        .seats {
            display: inline-block;
            background: #312e81;
            color: #c7d2fe;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 8px;
        }
        .seats.full { background: #7f1d1d; color: #fecaca; }

        /* Form */
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; font-size: 0.9rem; color: #94a3b8; }
        input, select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #475569;
            background: #0f172a;
            color: #f1f5f9;
            font-size: 1rem;
            transition: border 0.2s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.15s;
            margin-top: 8px;
        }
        button:hover { opacity: 0.92; transform: translateY(-1px); }
        button:active { transform: translateY(0); }

        /* Registrations Table */
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #334155; }
        th { color: #a5b4fc; font-weight: 600; }
        tr:hover td { background: #0f172a; }

        footer {
            text-align: center;
            padding: 30px;
            color: #64748b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<header>
    <h1> Event Registration Portal</h1>
    <p>Register for upcoming events in one click</p>
</header>

<div class="container">

    <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid">

        <!-- LEFT: Events + Form -->
        <div>
            <div class="card" style="margin-bottom:25px;">
                <h2> Upcoming Events</h2>
                <?php foreach ($events as $event): 
                    $available = $event['seats'] - $event['booked'];
                ?>
                    <div class="event-item">
                        <h3><?= htmlspecialchars($event['title']) ?></h3>
                        <p><?= htmlspecialchars($event['description']) ?></p>
                        <p> <?= date('d M Y', strtotime($event['event_date'])) ?> &nbsp;|&nbsp;  <?= htmlspecialchars($event['location']) ?></p>
                        <span class="seats <?= $available <= 0 ? 'full' : '' ?>">
                            <?= $available > 0 ? "$available seats left" : "Sold Out" ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h2> Register Now</h2>
                <form method="POST" id="regForm" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label>Select Event *</label>
                        <select name="event_id" id="event_id" required>
                            <option value="">-- Choose an event --</option>
                            <?php foreach ($events as $event): 
                                $available = $event['seats'] - $event['booked'];
                                if ($available > 0):
                            ?>
                                <option value="<?= $event['id'] ?>">
                                    <?= htmlspecialchars($event['title']) ?> (<?= $available ?> left)
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" id="full_name" placeholder="John Doe" required>
                    </div>

                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" id="email" placeholder="john@example.com" required>
                    </div>

                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" id="phone" placeholder="+91 98765 43210">
                    </div>

                    <div class="form-group">
                        <label>Number of Tickets</label>
                        <input type="number" name="tickets" id="tickets" value="1" min="1" max="10">
                    </div>

                    <button type="submit" name="register">Register for Event</button>
                </form>
            </div>
        </div>

        <!-- RIGHT: Recent Registrations -->
        <div class="card">
            <h2> Recent Registrations</h2>
            <?php if (empty($registrations)): ?>
                <p style="color:#94a3b8;text-align:center;padding:30px 0;">No registrations yet.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Event</th>
                                <th>Tickets</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $reg): ?>
                                <tr>
                                    <td><?= htmlspecialchars($reg['full_name']) ?></td>
                                    <td><?= htmlspecialchars($reg['event_title']) ?></td>
                                    <td><?= $reg['tickets'] ?></td>
                                    <td><?= date('d M', strtotime($reg['registered_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<footer>
    Event Registration Portal &copy; <?= date('Y') ?> | Built with PHP + MySQL
</footer>

<script>
function validateForm() {
    const name  = document.getElementById('full_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const event = document.getElementById('event_id').value;
    const tickets = parseInt(document.getElementById('tickets').value);

    if (!name || !email || !event) {
        alert('Please fill all required fields.');
        return false;
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        alert('Please enter a valid email address.');
        return false;
    }

    if (tickets < 1 || tickets > 10) {
        alert('Tickets must be between 1 and 10.');
        return false;
    }

    return true;
}

// Optional: Live character feedback
document.getElementById('full_name').addEventListener('input', function() {
    this.value = this.value.replace(/[^a-zA-Z\s.]/g, '');
});
</script>

</body>
</html>
