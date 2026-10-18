import { useState, useEffect, useRef } from '@wordpress/element';

export default function useDebounce( value, delay ) {
	const [ debounced, setDebounced ] = useState( value );
	const timerRef = useRef();

	useEffect( () => {
		timerRef.current = setTimeout( () => setDebounced( value ), delay );
		return () => clearTimeout( timerRef.current );
	}, [ value, delay ] );

	return debounced;
}
