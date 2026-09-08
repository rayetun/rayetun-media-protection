/**
 * Standard page header: title + optional subtitle + optional actions slot.
 */
export default function PageHeader( { title, subtitle, actions } ) {
	return (
		<header className="mg-page-header" style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 16 } }>
			<div>
				<h1 className="mg-page-header__title">{ title }</h1>
				{ subtitle && <p className="mg-page-header__subtitle">{ subtitle }</p> }
			</div>
			{ actions && <div className="mg-page-header__actions">{ actions }</div> }
		</header>
	);
}
