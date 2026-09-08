/**
 * Dashboard stat tile: a big number with a label.
 */
export default function StatCard( { value, label, tone = 'default' } ) {
	const color =
		tone === 'danger'
			? 'var(--mg-danger)'
			: tone === 'primary'
			? 'var(--mg-primary)'
			: 'var(--mg-text)';
	return (
		<div className="mg-card">
			<div style={ { fontSize: 30, fontWeight: 700, letterSpacing: '-0.02em', color } }>
				{ value }
			</div>
			<div style={ { color: 'var(--mg-text-muted)', fontSize: 13, marginTop: 4 } }>
				{ label }
			</div>
		</div>
	);
}
