<div class="container mt-5">
  <h3>Subscribe to Email Lists</h3>
  <form method="POST" action="add.php">
    <div class="mb-3">
      <label for="name" class="form-label">Name (optional)</label>
      <input type="text" class="form-control" name="name" placeholder="Jane Doe">
    </div>
    <div class="mb-3">
      <label for="email" class="form-label">Email address</label>
      <input type="email" class="form-control" name="email" required placeholder="you@example.com">
    </div>
    <div class="mb-3">
      <label for="lists" class="form-label">List IDs (comma-separated)</label>
      <input type="text" class="form-control" name="lists" required placeholder="abcd1234,efgh5678">
    </div>
    <button type="submit" class="btn btn-primary">Subscribe</button>
  </form>
</div>