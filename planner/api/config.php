<?php
// ══════════════════════════════════════════════════════════════════════════
//  MCA Student Planner — Server Configuration
//  Give this file to your IT department and ask them to fill in the values.
// ══════════════════════════════════════════════════════════════════════════

// ── Database ───────────────────────────────────────────────────────────────
// IT: fill in the hostname, database name, username, and password for the
// MySQL / MariaDB database you created for this app.
define('DB_HOST', 'localhost');         // usually 'localhost'
define('DB_NAME', 'mca_planner');       // the database name you created
define('DB_USER', 'planner_user');      // the DB user you created
define('DB_PASS', 'CHANGE_ME');         // ← IT: put the real password here

// ── Token Security ─────────────────────────────────────────────────────────
// Change this to any long random string (64+ characters).
// It is used to make auth tokens tamper-proof.
// You can generate one here: https://www.random.org/strings/
define('TOKEN_SECRET', 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING_AT_LEAST_64_CHARS');

// ── Session Length ─────────────────────────────────────────────────────────
// How many hours a "Stay signed in" token lasts before expiring.
define('TOKEN_EXPIRES_HOURS', 72);   // 72 hours = 3 days

// ── Admin Password ─────────────────────────────────────────────────────────
// This is the password for the teacher/admin dashboard at /planner/admin/
// IMPORTANT: Change this to something strong before going live.
// Store it somewhere safe — if you lose it, change it here and restart.
define('ADMIN_PASSWORD', 'CHANGE_THIS_ADMIN_PASSWORD');

// ── Admin Session Length ───────────────────────────────────────────────────
define('ADMIN_TOKEN_EXPIRES_HOURS', 8);  // Admin sessions expire after 8 hours
