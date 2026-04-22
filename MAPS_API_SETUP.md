# Maps API Integration Guide

## Overview
Maps have been integrated into the claim (sinistre) section using **100% free, open-source APIs**. Users can now add incident locations using an interactive OpenStreetMap picker with reverse geocoding powered by Nominatim.

**No API key is required!**

## Features

### 1. **Claim Form (`/sinistre/new` and edit):**
   - **Interactive Map**: Click anywhere on the map to place a marker for the incident location
   - **Address Search**: Press Enter in the location field to search for addresses
   - **Reverse Geocoding**: When clicking on the map, the system automatically converts coordinates to a full address
   - **Location Fields**: Three fields store the data:
     - `latitude`: Latitude coordinate
     - `longitude`: Longitude coordinate  
     - `location_address`: Human-readable address

### 2. **Claim Details Page (`/sinistre/{id}`):**
   - Displays the incident location on an interactive map
   - Shows the address if available
   - Displays the precise coordinates

## APIs Used

### OpenStreetMap
- **Purpose**: Map tiles and display
- **License**: Open Data Commons Open Database License (ODbL)
- **Cost**: FREE
- **Website**: https://www.openstreetmap.org/

### Nominatim
- **Purpose**: Forward and reverse geocoding
- **License**: Open Data Commons Open Database License (ODbL)
- **Cost**: FREE (with fair use policy)
- **Website**: https://nominatim.org/
- **Rate Limit**: 1 request per second per IP address
- **Terms**: Must provide proper attribution

### Leaflet
- **Purpose**: Interactive map library
- **License**: BSD 2-Clause
- **Cost**: FREE
- **Website**: https://leafletjs.com/

## Setup Instructions

### Step 1: No Configuration Needed!

Unlike Google Maps, there is **NO API key required**. The system is ready to use out of the box!

Simply navigate to:
- **File new claim**: http://127.0.0.1:8000/sinistre/new
- **View claim**: http://127.0.0.1:8000/sinistre/{id}
- **Edit claim**: http://127.0.0.1:8000/sinistre/{id}/edit

The maps will work immediately.

## Usage

### For Users Filing a Claim:

1. Navigate to `/sinistre/new`
2. Fill in the basic claim information
3. In the "Incident Location" section:
   - **Option A (Click Map)**: Click directly on the map where the incident occurred
   - **Option B (Search)**: Type an address in the location field and press Enter
4. The address and coordinates will be automatically populated
5. Submit the form

### For Users Viewing Claim Details:

1. Go to the claim details page
2. The incident location will be displayed on an interactive map
3. The address (if available) and exact coordinates are shown below the map

## Technical Details

### Files Modified:

1. **Templates**:
   - `templates/sinistre/form.html.twig`: Updated to use Leaflet + Nominatim
   - `templates/sinistre/show.html.twig`: Updated to use Leaflet for display

2. **Controller** (`src/Controller/SinistreController.php`):
   - Removed Google Maps API key requirement
   - Simplified render calls

3. **Configuration**:
   - Removed Google Maps API key from `.env`
   - Removed Google Maps config from `config/services.yaml`

### JavaScript Libraries Used:

- **Leaflet.js** (v1.9.4): Interactive mapping library
- **Nominatim API**: Geocoding via fetch API

### Location Data Validation:

- **Latitude**: Must be between -90 and 90
- **Longitude**: Must be between -180 and 180
- **Address**: Optional but recommended for UX

## Advantages of Free APIs

✅ **No API Key Required**: No registration or billing needed
✅ **No Quota Limits**: No daily request limits (fair use policy)
✅ **Open Source**: Full transparency and community support
✅ **Privacy-Friendly**: Data remains open and not tracked by corporations
✅ **Offline Ready**: Can be extended with offline map support
✅ **Zero Cost**: Perfect for small to medium applications

## API Features

### Forward Geocoding (Address Search)
Users can search for addresses in the location field. The system will:
1. Send the address to Nominatim
2. Receive coordinates back
3. Center the map on the result
4. Place a marker at the location

### Reverse Geocoding (Coordinate → Address)
When users click on the map:
1. Coordinates are sent to Nominatim
2. A full address is returned
3. The address field is auto-populated
4. A marker appears on the map

## Rate Limiting & Fair Use

**Nominatim Free API Policy:**
- Limit: 1 request per second per IP address
- For higher rates, consider the [Nominatim API key](https://nominatim.org/contact/) option
- The system respects this by only making requests when users interact with the map

**Recommended for Production:**
- Implement caching on the server side
- Cache reverse geocoding results
- Consider upgrading to Nominatim's commercial API for higher traffic

## Troubleshooting

### Map Not Showing
- Ensure JavaScript is enabled
- Check browser console for errors
- Verify Leaflet CSS and JS are loaded from CDN

### Address Search Not Working
- Verify the address exists in OpenStreetMap data
- Try searching with different address format
- Check browser network tab for 429 (rate limit) errors

### Reverse Geocoding Returns Partial Address
- This is normal for remote areas with less OpenStreetMap data
- The system falls back to coordinates (lat, lng)
- Consider contributing to OpenStreetMap to improve data

### Rate Limiting Issues
- If you see many 429 errors, your server may be making too many requests
- Implement request caching
- Add delays between rapid requests
- Contact Nominatim for commercial API access

## Security Notes

- Geocoding data is transmitted over HTTPS
- No API keys stored in code or configuration
- User location data is stored only in the database
- Consider GDPR compliance when storing location data

## Performance Considerations

- Map initialization: ~500ms
- Address search: 1-2 seconds (rate limited to 1 req/sec)
- Reverse geocoding: 1-2 seconds
- For better performance, implement:
  - Server-side caching
  - Debounced search input
  - Lazy loading of maps

## Future Enhancements

Possible improvements:
1. Server-side geocoding cache
2. Offline map tiles support
3. Multiple location selection for complex incidents
4. Route display from police station to incident
5. Clustering for nearby incidents
6. Address autocomplete dropdown
7. Distance calculation from key locations

## Support

For issues with free map APIs:
1. Check OpenStreetMap coverage: https://www.openstreetmap.org/
2. Verify Nominatim status: https://nominatim.org/
3. Check browser console for JavaScript errors
4. Review Leaflet documentation: https://leafletjs.com/
5. Check Nominatim API docs: https://nominatim.org/

## License Compliance

All used APIs and libraries are open source:
- OpenStreetMap data: ODbL
- Leaflet: BSD 2-Clause
- Nominatim: ODbL

Proper attribution is included in the code comments.

## Attribution

Map data © [OpenStreetMap](https://www.openstreetmap.org/) contributors
Geocoding by [Nominatim](https://nominatim.org/)
Maps by [Leaflet](https://leafletjs.com/)
