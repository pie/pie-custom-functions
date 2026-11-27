/**
 * The site is up. A first PressTest test for the project, to build on (tests/README.md).
 */
const { test, expect } = require( 'presstest' );

test.describe( 'The home page', () => {
	test( 'loads without an error', async ( { page } ) => {
		const response = await page.goto( '/' );

		expect( response.status() ).toBeLessThan( 400 );
		await expect( page.locator( 'body' ) ).not.toContainText( /Fatal error|There has been a critical error/ );
	} );
} );
