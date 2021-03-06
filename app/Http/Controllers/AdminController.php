<?php

namespace FreshmanGuide\Http\Controllers;

use Illuminate\Http\Request;

use FreshmanGuide\Http\Requests;
use FreshmanGuide\Article;
use FreshmanGuide\Section;
use FreshmanGuide\Comment;
use FreshmanGuide\Exceptions\AdminException;
use Yajra\Datatables\Datatables;
use Image;

class AdminController extends Controller
{

    public function getLogin() {
        return view('admin.login');
    }

    public function sendResponse(Request $request, $data, $view = null) {
        if ($request->ajax()) {
            return response()->json($data);
        } else {
            if (isset($view)) {
                return view($view, $data);
            }
            return back()->with([
                'success' => $data['message'],
            ]);
        }
    }

    public function home(Request $request) {
        return view('admin.home', [
            'title' => 'Admin',
        ]);
    }

    public function articles(Request $request) {
        $sections = Section::select('id', 'name')->get();

        return view('admin.articles', [
            'sections' => $sections
        ]);

    }

    public function articlesData(Request $request) {
        $articles = Article::select(['id', 'title', 'searchid', 'updated_at', 'published', 'slug'])->orderBy('updated_at', 'desc');

        return Datatables::of($articles)
            ->addColumn('action', function($article) {
                $html = '<div class="btn-group" role="group" >' .
                    '<a href="' . url('edit') . '/' . $article->searchid . '" class="btn btn-sm btn-primary" target="_blank" > Edit </a>' .
                    '<a href="' . url('admin/delete') . '/' . $article->searchid . '" data-id="' . $article->searchid . '" class="delete btn btn-sm btn-danger" target="_blank" data-title="' . $article->title . '"> Delete </a>' .
                    '<a href="' . url('admin/comments') . '/' . $article->searchid . '" data-id="' . $article->searchid . '" class="btn btn-sm btn-primary" target="_blank" data-title="' . $article->title . '"> Comments (' . $article->count . ') </a>';

                if ($article->published) {
                    $html .= '<a href="' . url('admin/unpublish') . '/' . $article->searchid . '" data-id="' . $article->searchid . '" class="unpublish btn btn-sm btn-warning" target="_blank" data-title="' . $article->title . '"> Unpublish </a>' .
                    '<a href="' . url('read') . '/' . $article->slug . '" data-id="' . $article->searchid . '" class="btn btn-sm btn-success" target="_blank" data-title="' . $article->title . '"> Read </a>';
                } else {
                    $html .= '<a href="' . url('admin/publish') . '/' . $article->searchid . '" data-id="' . $article->searchid . '" class="publish btn btn-sm btn-success" target="_blank" data-title="' . $article->title . '"> Publish </a>';
                }

                return $html . '</div>';

            })
            ->editColumn('published', function($article) {
                return $article->status;
            })
            ->editColumn('section', function($article) {
                if (!$article->section) {
                    return '<a href="' . url('admin/categorize') . '/' . $article->searchid . '" data-id="' . $article->searchid . '" class="change-section btn btn-sm btn-success" target="_blank" data-title="' . $article->title . '"> Categorize </a>';
                } else {
                    return '<span ' . 'data-id="' . $article->searchid . '" class="section">' .  $article->section->name . '</span><a href="' . url('admin/categorize') . '/' . $article->searchid . '" data-id="' . $article->searchid . '" class="change-section button-edit btn btn-sm btn-success" target="_blank" data-title="' . $article->title . '"> <i class="fa fa-pencil"></fa> </a>';
                }
            })
            ->make(true);
    }


    public function delete(Request $request, $searchid) {
        $article = Article::where('searchid', $searchid)->first();
        if (!$article) {
            throw new AdminException('No such article', 1);
        }

        if ($article->delete()) {
            return $this->sendResponse($request, [
                'success' => true,
                'message' => 'Article deleted'
            ]);
        } else {
            throw new AdminException("Unable to delete the article", 1);
        }
    }


    public function publish(Request $request, $searchid) {
        $article = Article::where('searchid', $searchid)->first();
        if (!$article) {
            throw new AdminException('No such article', 1);
        }
        $article->published = true;
        $article->edited = false;
        $article->new = false;
        if ($article->save()) {
            return $this->sendResponse($request, [
                'success' => true,
                'message' => 'Article published'
            ]);
        } else {
            throw new AdminException("Unable to publish the article", 1);
        }
    }

    public function unpublish(Request $request, $searchid) {
        $article = Article::where('searchid', $searchid)->first();
        if (!$article) {
            throw new AdminException('No such article', 1);
        }
        $article->published = false;
        $article->edited = false;
        $article->new = true;
        if ($article->save()) {
            return $this->sendResponse($request, [
                'success' => true,
                'message' => 'Article unpublished'
            ]);
        } else {
            throw new AdminException("Unable to unpublish the article", 1);
        }
    }

    public function logout(Request $request) {
        \Auth::logout();
        return redirect()->to('/');
    }

    public function sections(Request $request) {
        $sections = Section::orderBy('updated_at', 'desc')->get();
        if (!$sections) {
            throw new AdminException('Unable to fetch the articles', 0);
        }

        // public function responsehandler

        // serialize articles
        return $this->sendResponse($request, [
            'success' => true,
            'sections' => $sections,
        ], 'admin.sections.list');

    }

    public function addSection(Request $request) {
        return view('admin.sections.add');
    }

    public function createSection(Request $request) {

        if (!$request->input('name') || !$request->input('desc')) {
            throw new AdminException("Please enter all files", 1);
        }

        $section = new Section();
        $section->name = $request->input('name');
        $section->description = $request->input('desc');

        if ($request->file('image')) {
            $image = $request->file('image');
            $savePath =  '/images/sections/' . $section->id . '.' . $image->getClientOriginalExtension();
            $image = Image::make($image);
            $image->resize(400, 300)->save(public_path() . $savePath);
            $section->image = $savePath;
        }

        if ($section->save()) {
            return redirect('/admin/sections/')->with([
                'success' => 'Section created',
            ]);
        } else {
            throw new AdminException("Unable to create new section", 1);

        }

    }

    public function deleteSection(Request $request, $id) {
        $section = Section::where('id', $id)->first();

        if (!$section) {
            throw new AdminException("No such section", 1);
        }

        if ($section->delete()) {
            return $this->sendResponse($request, [
                'success' => true,
                'message' => 'Section deleted'
            ]);
        } else {
            throw new AdminException("Unable to delete the section", 1);
        }

    }

    public function editSection(Request $request, $id) {
        $section = Section::where('id', $id)->first();

        if (!$section) {
            throw new AdminException("No such sections", 1);
        }

        return $this->sendResponse($request, [
            'section' => $section,
        ], 'admin.sections.edit');

    }

    public function updateSection(Request $request, $id) {
        if (!$request->input('name') || !$request->input('desc')) {
            throw new AdminException("Please enter all files", 1);
        }

        $section = Section::where('id', $id)->first();

        if (!$section) {
            throw new AdminException("No such sections", 0);
        }

        $section->name = $request->input('name');
        $section->description = $request->input('desc');

        if ($request->file('image')) {
            $image = $request->file('image');
            $savePath =  '/images/sections/' . $section->id . '.' . $image->getClientOriginalExtension();
            $image = Image::make($image);
            $image->resize(400, 300)->save(public_path() . $savePath);
            $section->image = $savePath;
        }

        if ($section->save()) {
            return redirect('/admin/sections/')->with([
                'success' => 'Section Updated',
            ]);
        } else {
            throw new AdminException("Unable to update the section", 1);
        }

    }

    public function categorize(Request $request, $searchid) {
        $article = Article::where('searchid', $searchid)->first();
        if (!$article) {
            throw new AdminException('No such article', 0);
        }

        $sections = Section::all();
        if (!$sections) {
            throw new AdminException('Unable to fetch sections', 0);
        }

        return $this->sendResponse($request, [
            'article' => $article,
            'sections' => $sections,
        ], 'admin.articles.categorize');

    }

    public function addCategory(Request $request, $searchid) {
        $article = Article::where('searchid', $searchid)->first();
        if (!$article) {
            throw new AdminException('No such article', 0);
        }

        $section = Section::where('id', $request->input('section'))->first();
        if (!$section) {
            throw new AdminException('No such section you are trying to add', 0);
        }

        $article->sections()->detach();
        $article->sections()->attach($section);

        return $this->sendResponse($request, [
            'success' => true,
            'message' => "Section addded",
        ]);


    }


    public function getComments(Request $request, $searchid) {
        $article = Article::where('searchid', $searchid)->first();
        if (!$article) {
            throw new AdminException('No such article', 0);
        }

        return $this->sendResponse($request, [
            'article' => $article,
            'comments' => $article->comments()->orderBy('updated_at', 'decr')->get(),
        ], 'admin.articles.comments');
    }


    public function postReply(Request $request, $commentId) {
        if ($request->input('reply') == '') {
            return reponse()->json([
                'success' => false,
                'error' => 'Please enter a reply',
            ]);
        }

        $comment = Comment::where('id', $commentId)->first();
        if (!$comment) {
            throw new AdminException('Comment Not Found', 0);
        }

        $comment->reply = $request->input('reply');
        if ($comment->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Reply has been added'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'error' => 'Reply can not be saved, please forgive!',
            ]);
        }
    }


    public function deleteComment(Request $request, $commentId) {
        $comment = Comment::where('id', $commentId)->first();
        if (!$comment) {
            throw new AdminException('Comment Not Found', 0);
        }

        if ($comment->delete()) {
            return response()->json([
                'success' => true,
                'message' => 'Comment has been deleted'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'error' => 'Unable to delete comment, please forgive!',
            ]);
        }   
    }

}
