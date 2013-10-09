<?hh

class Triangle
{
    private $colors = array("yellowgreen", "tomato", "plum");
    private $vertices;

    public function __construct(array $vertices)
    {
        assert(sizeof($vertices) == 3);
        $this->vertices = $vertices;
    }

    public function is_right()
    {
        return $this->has_horizontal_leg() && $this->has_vertical_leg();
    }

    private function has_horizontal_leg()
    {
        return sizeof(array_unique(array_map(function ($x) { return round($x->y, 4);}, $this->vertices))) < 3;
    }

    private function has_vertical_leg()
    {
        return sizeof(array_unique(array_map(function ($x) { return round($x->x, 4);}, $this->vertices))) < 3;
    }

    public function area()
    {
        return abs(
            (($this->vertices[0]->x - $this->vertices[2]->x) * ($this->vertices[1]->y - $this->vertices[0]->y) -
             ($this->vertices[0]->x - $this->vertices[1]->x) * ($this->vertices[2]->y - $this->vertices[0]->y)) * 0.5
        );
    }

    private function max_x()
    {
        return max(array_map(function ($x) { return $x->x; }, $this->vertices));
    }

    private function max_y()
    {
        return max(array_map(function ($x) { return $x->y; }, $this->vertices));
    }

    private function min_x()
    {
        return min(array_map(function ($x) {return $x->x; }, $this->vertices));
    }

    private function min_y()
    {
        return min(array_map(function ($x) { return $x->y; }, $this->vertices));
    }

    private function vertical_cross_lines()
    {
        return array_map(function ($x) {
            return new Line(new Point($x->x, $this->min_y()), new Point($x->x, $this->max_y()));
        }, $this->vertices);
    }

    private function horizontal_cross_lines()
    {
        return array_map(function ($x) {
            return new Line(new Point($this->min_x(), $x->y), new Point($this->max_x(), $x->y));
        }, $this->vertices);
    }

    private function select_crossline()
    {
        $comp1 = function($a, $b)
        {
            if ($a->a->x > $b->a->x) {
                return 1;
            } else if ($a->a->x < $b->a->x) {
                return -1;
            } else {
                return 0;
            }
        };

        $comp2 = function($a, $b)
        {
            if ($a->a->y > $b->a->y) {
                return 1;
            } else if ($a->a->y < $b->a->y) {
                return -1;
            } else {
                return 0;
            }
        };

        if ($this->has_horizontal_leg()) {
            $lines = $this->vertical_cross_lines();
            usort($lines, $comp1);
        } else {
            $lines = $this->horizontal_cross_lines();
            usort($lines, $comp2);
        }
        return $lines[1];
    }

    public function split_triangle()
    {
        $crossline = $this->select_crossline();
        assert(!is_null($crossline));

        $split_vertex = array_values(array_filter($this->vertices, function ($x) use ($crossline) {
            return $crossline->is_online($x);
        }));
        $other_vertices = array_values(array_filter($this->vertices, function ($x) use ($crossline) {
            return !$crossline->is_online($x);
        }));

        assert(sizeof($split_vertex) == 1);
        assert(sizeof($other_vertices) == 2);
        assert($other_vertices[0] != $other_vertices[1]);

        $cross_point = (new Line($other_vertices[0], $other_vertices[1]))->intersection_point($crossline);

        $first =  new Triangle(array($split_vertex[0], $cross_point, $other_vertices[0]));
        $second = new Triangle(array($split_vertex[0], $cross_point, $other_vertices[1]));

        return array($first, $second);
    }

    public function svg()
    {
        return sprintf("<polygon points='%f,%f %f,%f %f,%f' style='fill:%s;stroke:white'/>",
            $this->vertices[0]->x, $this->vertices[0]->y,
            $this->vertices[1]->x, $this->vertices[1]->y,
            $this->vertices[2]->x, $this->vertices[2]->y,
            $this->colors[array_rand($this->colors, 1)]
        );
    }
}

class Point
{
    public float $x = 0.0;
    public float $y = 0.0;

    public function __construct(float $x, float $y)
    {
        $this->x = $x;
        $this->y = $y;
    }
}

class Line
{

    const float EPSILON = 0.0001;
    public Point $a;
    public Point $b;

    public function __construct(Point $a, Point $b)
    {
        $this->a = $a;
        $this->b = $b;
    }

    private function linear_equation() : array<float>
    {
        $divisor = ($this->b->x - $this->a->x);
        if ($divisor != 0) {
            $k = ($this->b->y - $this->a->y) / $divisor;
        } else {
            $k = INF;
        }
        $d = $this->a->y - $k * $this->a->x;
        return array($k, $d);
    }

    public function is_online($r) : bool
    {
        list($slope, $intercept) = $this->linear_equation();

        if (is_infinite($slope)) {
            $error = $this->a->x - $r->x;
        } else if (is_infinite($intercept)) {
            $error = $this->a->y - $r->y;
        } else {
            $error = $r->y - ($slope * $r->x + $intercept);
        }

        return (abs($error) < self::EPSILON);
    }

    public function intersection_point($other) : Point
    {
        list($this_slope, $this_intercept) = $this->linear_equation();
        list($other_slope, $other_intercept) = $other->linear_equation();

        if (is_infinite($this_slope)) {
            $x = $this->a->x;
            $y = $other_slope * $x + $other_intercept;
        } else if (is_infinite($other_slope)) {
            $x = $other->a->x;
            $y = $this_slope * $x + $this_intercept;
        } else {
            $x = ($other_intercept - $this_intercept) / ($this_slope - $other_slope);
            $y = $this_slope * $x + $this_intercept;
        }
        return new Point($x, $y);
    }
}

// Load the data file
function load(string $file) : array<Triangle>
{
    $get_triangles = function(array<Triangle> $acc, string $line) : array<Triangle>
    {
        $matches = array();

        $pattern_points = '/"({.*,.*})","({.*,.*})","({.*,.*})"/';
        
        $matches = array();
        preg_match($pattern_points, $line, $matches);

        $points = array_map(function ($x) : Point {
            $matches = array();
            $pattern_point = '/{(.*),(.*)}/';
            $matches = array();
            preg_match($pattern_point, $x, $matches);
            return new Point(
                ($matches[1] * 800) + 600,
                ($matches[2] * -800) + 1050
            );
        }, array_slice($matches, 1));

        if (sizeof($points) != 3) {
            return $acc;
        }

        $trg = new Triangle($points);
        array_push($acc, $trg);
        return $acc;
    };

    return array_reduce(file($file), $get_triangles, array());
}

function split_triangles(Triangle $t) : array<Triangle>
{
    if ($t->is_right() || $t->area() < 1) {
        return array($t);
    } 

    return array_flatten(array_map(function ($trg) {return split_triangles($trg);}, $t->split_triangle()));
}

// TODO: make generic
function array_flatten(array $arr) : array {
    $arr = array_values($arr);
    while (list($k,$v)=each($arr)) {
        if (is_array($v)) {
            array_splice($arr,$k,1,$v);
            next($arr);
        }
    }
    return $arr;
}

function print_err(string $str) : void {
    $stderr = fopen('php://stderr', 'w');
    fwrite($stderr, $str);
    fclose($stderr);
}

function bench($op) : int {
    $time_start = microtime(true);
    $op();
    $time_end = microtime(true);
    return $time_end - $time_start;
}

function main() : void {
    $file = "teapot.txt";

    $time_start = microtime(true);
    $triangles = load($file);
    $time = (microtime(true) - $time_start) * 1000;
    print_err("Loaded " . sizeof($triangles) . " triangles in " . $time . " ms\n");

    $time_start = microtime(true);
    $right_triangles = array_flatten(array_map(function ($trg) {
        return split_triangles($trg);
    }, $triangles));$time = microtime(true) - $time_start;
    $time = (microtime(true) - $time_start) * 1000;
    print_err("Generated " . sizeof($right_triangles) . " triangles in " . $time . " ms\n");

    print("<svg xmlns='http://www.w3.org/2000/svg' version='1.1'>");
        foreach($right_triangles as $triangle) {
            print($triangle->svg()."\n");
        }
    print('</svg>');
}

main();

