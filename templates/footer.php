<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<div style="min-height:40px;"></div>

<footer class="tcs-footer">
    <div class="tcs-footer-inner">
        <div class="tcs-footer-left">
            <span class="tcs-status-dot"></span>
            <span class="tcs-status-text">All systems operational</span>
        </div>

        <div class="tcs-footer-middle d-none d-sm-flex">
            <span class="tcs-footer-pill">
                <i class="bi bi-activity me-1"></i>
                Last sync &mdash; <?= date('H:i') ?>
            </span>
        </div>

        <div class="tcs-footer-right">
            <span class="tcs-footer-copy">
                &copy; <?= date('Y') ?> EVERSTONE TECHNOLOGY SYSTEMS INC. · All rights reserved
            </span>
        </div>
    </div>
</footer>

</body>
</html>
