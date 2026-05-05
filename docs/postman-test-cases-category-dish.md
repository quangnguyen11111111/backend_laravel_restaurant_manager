# Postman test cases - Category & Dish APIs

## Base

- Base URL: {{base_url}}/api
- Admin endpoints: require JWT auth and role Owner
- Validation errors: HTTP 422 with { message, statusCode, errors[] }
- Service errors: statusCode matches error (400/404/500)

## Category APIs (public)

- GET /categories -> 200, list tree; verify children field only when has children
- GET /categories -> 200 with empty list when no data
- GET /categories/{id} valid -> 200 with category data
- GET /categories/{id} not found -> 404
- GET /categories/{id} non-number -> 404 (route not matched)

## Category APIs (admin)

- GET /admin/categories page=1 -> 200 with pagination
- GET /admin/categories page>last -> 200 with empty data, pagination still valid
- GET /admin/categories page invalid (string or <1) -> 422
- GET /admin/categories no auth -> 401
- GET /admin/categories wrong role -> 403
- POST /admin/categories minimal body -> 200, data created
- POST /admin/categories with optional fields (status, order, parent_id) -> 200
- POST /admin/categories missing name -> 422
- POST /admin/categories name >256 -> 422
- POST /admin/categories parent_id not integer -> 422
- POST /admin/categories parent_id not exists -> 422
- POST /admin/categories status not in Active/Inactive -> 422
- POST /admin/categories order negative -> 422
- POST /admin/categories no auth / wrong role -> 401/403
- PUT /admin/categories/{id} update name -> 200
- PUT /admin/categories/{id} set parent_id to null -> 200
- PUT /admin/categories/{id} not found -> 404
- PUT /admin/categories/{id} parent_id == id -> 400
- PUT /admin/categories/{id} parent_id not exists -> 422
- PUT /admin/categories/{id} status invalid -> 422
- PUT /admin/categories/{id} order negative -> 422
- PUT /admin/categories/{id} no auth / wrong role -> 401/403
- DELETE /admin/categories/{id} -> 200
- DELETE /admin/categories/{id} not found -> 404
- DELETE /admin/categories/{id} no auth / wrong role -> 401/403

### Category request bodies

- POST /admin/categories (JSON)

```json
{
    "name": "Main dishes",
    "parent_id": 1,
    "status": "Active",
    "order": 1
}
```

- POST /admin/categories minimal (JSON)

```json
{
    "name": "Drinks"
}
```

- PUT /admin/categories/{id} (JSON - partial update)

```json
{
    "name": "New name",
    "parent_id": null,
    "status": "Inactive"
}
```

## Dish APIs (public)

- GET /dishes?category_id=1 -> 200 with pagination
- GET /dishes?category_id missing -> 400 (category_id invalid)
- GET /dishes?category_id=abc -> 422
- GET /dishes?category_id=9999 (not exists) -> 200, empty list
- GET /dishes?page=0 -> 422
- GET /dishes ensures status Hidden is excluded
- GET /dishes/{id} valid -> 200
- GET /dishes/{id} not found -> 404
- GET /dishes/{id} non-number -> 404

## Dish APIs (admin)

- GET /admin/dishes page=1 -> 200 with pagination
- GET /admin/dishes page invalid -> 422
- GET /admin/dishes no auth / wrong role -> 401/403
- POST /admin/dishes valid body -> 200
- POST /admin/dishes missing name/price/description/image/category_id -> 422
- POST /admin/dishes price not integer or <1 -> 422
- POST /admin/dishes description >10000 -> 422
- POST /admin/dishes image not URL -> 422
- POST /admin/dishes imageS3Key missing when image present -> 422
- POST /admin/dishes imageS3Key too long -> 422
- POST /admin/dishes status not in Available/Unavailable/Hidden -> 422
- POST /admin/dishes category_id not exists -> 422
- POST /admin/dishes no auth / wrong role -> 401/403
- PUT /admin/dishes/{id} valid update -> 200
- PUT /admin/dishes/{id} missing category_id -> 422
- PUT /admin/dishes/{id} invalid category_id -> 422
- PUT /admin/dishes/{id} update image with imageS3Key -> 200, imageS3Key in response updated
- PUT /admin/dishes/{id} update image URL without imageS3Key -> 200, imageS3Key becomes null
- PUT /admin/dishes/{id} status invalid -> 422
- PUT /admin/dishes/{id} not found -> 404
- PUT /admin/dishes/{id} no auth / wrong role -> 401/403
- DELETE /admin/dishes/{id} -> 200
- DELETE /admin/dishes/{id} not found -> 404
- DELETE /admin/dishes/{id} no auth / wrong role -> 401/403

### Dish request bodies

- POST /admin/dishes (JSON)

```json
{
    "name": "Fried rice",
    "price": 45000,
    "description": "House special fried rice",
    "image": "{{uploaded_image_url}}",
    "imageS3Key": "{{uploaded_image_key}}",
    "status": "Available",
    "category_id": 1
}
```

- PUT /admin/dishes/{id} (JSON - minimal update)

```json
{
    "category_id": 1,
    "price": 49000,
    "status": "Available"
}
```

- PUT /admin/dishes/{id} (JSON - update image from upload)

```json
{
    "category_id": 1,
    "image": "{{uploaded_image_url}}",
    "imageS3Key": "{{uploaded_image_key}}"
}
```

- PUT /admin/dishes/{id} (JSON - update image by URL, no imageS3Key)

```json
{
    "category_id": 1,
    "image": "https://example.com/new-image.jpg"
}
```

## Dish image upload (admin)

- POST /admin/dishes/image with valid image -> 200, returns image + imageS3Key
- POST /admin/dishes/image missing file -> 422
- POST /admin/dishes/image wrong mime -> 422
- POST /admin/dishes/image file >2MB -> 422
- POST /admin/dishes/image no auth / wrong role -> 401/403
- DELETE /admin/dishes/image with valid imageS3Key -> 200
- DELETE /admin/dishes/image missing imageS3Key -> 422
- DELETE /admin/dishes/image invalid imageS3Key -> 422
- DELETE /admin/dishes/image no auth / wrong role -> 401/403

### Dish image request bodies

- POST /admin/dishes/image (form-data)

```
key: image
type: file
value: <choose a jpeg/png/jpg/webp file <= 2MB>
```

- DELETE /admin/dishes/image (JSON)

```json
{
    "imageS3Key": "{{uploaded_image_key}}"
}
```
